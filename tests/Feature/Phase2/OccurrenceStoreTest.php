<?php

declare(strict_types=1);

use G8Scope\Core\Contracts\OccurrenceStore;
use G8Scope\Core\Contracts\OccurrenceWriter;
use G8Scope\Core\Models\Account;
use G8Scope\Core\Stores\PostgresOccurrenceStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->writer = app(OccurrenceWriter::class);
    $this->reader = app(OccurrenceStore::class);
    $project = Account::create(['name' => 'DevHub'])->projects()->create(['name' => 'P']);
    $this->projectId = $project->id;
    $this->uuid = $project->issues()->create(['fingerprint' => 'fp', 'type' => 'E'])->uuid;
    $this->row = fn (array $attrs = []) => array_merge([
        'project_id' => $this->projectId,
        'issue_uuid' => $this->uuid,
        'fingerprint' => 'fp',
        'event_id' => (string) Str::uuid(),
        'level' => 'error',
        'message' => 'x',
        'occurred_at' => now()->subHour()->format('Y-m-d H:i:s.v\Z'),
    ], $attrs);
});

it('binds the Postgres store for both read and write contracts', function (): void {
    expect($this->reader)->toBeInstanceOf(PostgresOccurrenceStore::class)
        ->and($this->writer)->toBe($this->reader);
});

it('records an occurrence and reads it back', function (): void {
    $this->writer->record(($this->row)(['environment' => 'production', 'message' => 'boom', 'payload' => ['k' => 'v']]));

    $rows = [...$this->reader->forIssue($this->uuid, 10)];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['message'])->toBe('boom')
        ->and(json_decode($rows[0]['payload'], true))->toBe(['k' => 'v']);
});

it('is idempotent on a replayed event (same event_id + occurred_at)', function (): void {
    $row = ($this->row)();
    $this->writer->record($row);
    $this->writer->record($row);

    expect(DB::table('occurrences')->count())->toBe(1);
});

it('returns the newest occurrences first, limited', function (): void {
    foreach ([3, 1, 2] as $h) {
        $this->writer->record(($this->row)(['message' => "h{$h}", 'occurred_at' => now()->subHours($h)->format('Y-m-d H:i:s.v\Z')]));
    }

    $rows = [...$this->reader->forIssue($this->uuid, 2)];
    expect(array_column($rows, 'message'))->toBe(['h1', 'h2']);
});

it('builds a sparkline grouped by hour', function (): void {
    $base = now()->utc()->subHours(5)->startOfHour();
    foreach ([0, 30, 120] as $min) {
        $this->writer->record(($this->row)(['occurred_at' => $base->copy()->addMinutes($min)->format('Y-m-d H:i:s.v\Z')]));
    }

    $spark = $this->reader->sparkline($this->uuid, '1h');

    expect($spark)->toBe([
        ['bucket' => $base->format('Y-m-d H:i:s'), 'count' => 2],
        ['bucket' => $base->copy()->addHours(2)->format('Y-m-d H:i:s'), 'count' => 1],
    ]);
});

it('counts events over time across projects and per release', function (): void {
    $this->writer->record(($this->row)(['release' => 'r1']));
    $this->writer->record(($this->row)(['release' => 'r1']));
    $this->writer->record(($this->row)(['release' => 'r2', 'occurred_at' => now()->subDays(5)->format('Y-m-d H:i:s.v\Z')]));

    $store = app(PostgresOccurrenceStore::class);

    expect(array_sum(array_column($store->eventsOverTime([$this->projectId], '1h', 48), 'count')))->toBe(2)
        ->and($store->eventsOverTime([]))->toBe([])
        ->and($store->countForRelease($this->projectId, 'r1'))->toBe(2);
});

it('counts occurrences per project since a point in time', function (): void {
    $this->writer->record(($this->row)());
    $this->writer->record(($this->row)());
    $this->writer->record(($this->row)(['occurred_at' => now()->subDays(3)->format('Y-m-d H:i:s.v\Z')]));

    $counts = app(PostgresOccurrenceStore::class)->countsSince([$this->projectId, 999], now()->subDay());

    expect($counts)->toBe([$this->projectId => 2])
        ->and(app(PostgresOccurrenceStore::class)->countsSince([], now()))->toBe([]);
});
