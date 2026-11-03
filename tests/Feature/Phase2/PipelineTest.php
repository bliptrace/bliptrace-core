<?php

declare(strict_types=1);

use G8Scope\Core\Actions\RecordOccurrence;
use G8Scope\Core\Contracts\OccurrenceStore;
use G8Scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::fake();
    $account = Account::create(['name' => 'DevHub']);
    $this->project = $account->projects()->create(['name' => 'P']);
    $this->project->alertRules()->create([
        'channel' => 'webhook', 'target' => ['url' => 'https://hooks.test/a'], 'enabled' => true,
    ]);
    $this->record = app(RecordOccurrence::class);
});

function envelope(string $eventId): array
{
    return [
        'event_id' => $eventId,
        'occurred_at' => '2026-06-25T09:14:32.521Z',
        'runtime' => ['name' => 'php'],
        'level' => 'error',
        'environment' => 'production',
        'exception' => [
            'type' => 'RuntimeException',
            'message' => 'AD write failed',
            'frames' => [['file' => '/app/Actions/Reset.php', 'line' => 42, 'function' => 'Reset::__invoke', 'in_app' => true]],
        ],
        'context' => ['user' => ['password' => 'hunter2']],
    ];
}

it('runs scrub → fingerprint → issue → occurrence → alert end-to-end', function (): void {
    $issue = ($this->record)($this->project->id, envelope((string) Str::uuid()));

    expect($issue->occurrences_count)->toBe(1)
        ->and($issue->type)->toBe('RuntimeException');

    // occurrence landed in Postgres with PII scrubbed in the payload
    $rows = iterator_to_array((fn () => yield from app(OccurrenceStore::class)->forIssue($issue->uuid, 10))());
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['payload'])->not->toContain('hunter2')
        ->and($rows[0]['payload'])->toContain('[REDACTED]');

    // one alert for the new issue
    Http::assertSentCount(1);
});

it('stores an event_id whose last group is all digits (not masked by the scrubber)', function (): void {
    $id = '86be23b5-5e80-4186-8020-161900506889';

    $issue = ($this->record)($this->project->id, envelope($id));

    $rows = [...app(OccurrenceStore::class)->forIssue($issue->uuid, 10)];
    expect(DB::getDriverName())->toBe('pgsql')
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['event_id'])->toBe($id)
        ->and($rows[0]['payload'])->not->toContain('hunter2');
});

it('records a replayed event_id once (idempotent retry)', function (): void {
    $id = (string) Str::uuid();
    ($this->record)($this->project->id, envelope($id));
    $issue = ($this->record)($this->project->id, envelope($id));

    expect([...app(OccurrenceStore::class)->forIssue($issue->uuid, 10)])->toHaveCount(1);
});

it('collapses a flood into one alert (smart grouping)', function (): void {
    foreach (range(1, 5) as $i) {
        $issue = ($this->record)($this->project->id, envelope((string) Str::uuid()));
    }

    expect($issue->occurrences_count)->toBe(5);
    Http::assertSentCount(1); // only the first (new issue) alerts
});
