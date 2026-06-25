<?php

declare(strict_types=1);

use G8Scope\Core\Alerting\AlertDispatcher;
use G8Scope\Core\Contracts\IssueStore;
use G8Scope\Core\Contracts\Notifier;
use G8Scope\Core\Enums\IssueStatus;
use G8Scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::fake();
    $account = Account::create(['name' => 'DevHub']);
    $this->project = $account->projects()->create(['name' => 'P']);
    $this->project->alertRules()->create([
        'channel' => 'webhook',
        'target' => ['url' => 'https://hooks.test/alert'],
        'enabled' => true,
    ]);
    $this->store = app(IssueStore::class);
    $this->dispatcher = app(AlertDispatcher::class);
});

it('throws on an unknown channel', function (): void {
    expect(fn () => app(Notifier::class)->notify('carrier-pigeon', []))
        ->toThrow(InvalidArgumentException::class);
});

it('alerts on a new issue and notifies enabled rules', function (): void {
    $issue = $this->store->upsert($this->project->id, 'fp', ['type' => 'E', 'message' => 'boom']);
    $event = $this->dispatcher->dispatchFor($issue, $issue->wasRecentlyCreated, $issue->justRegressed);

    expect($event)->toBe('new_issue');
    Http::assertSent(fn ($r) => $r->url() === 'https://hooks.test/alert');
});

it('does NOT alert on a recurring occurrence (smart grouping)', function (): void {
    $this->store->upsert($this->project->id, 'fp', ['type' => 'E']);
    $again = $this->store->upsert($this->project->id, 'fp', ['type' => 'E']);

    $event = $this->dispatcher->dispatchFor($again, $again->wasRecentlyCreated, $again->justRegressed);
    expect($event)->toBeNull();
});

it('alerts on a regression', function (): void {
    $issue = $this->store->upsert($this->project->id, 'fp', ['type' => 'E']);
    $this->store->setStatus($issue->uuid, IssueStatus::Resolved);
    $reopened = $this->store->upsert($this->project->id, 'fp', ['type' => 'E']);

    $event = $this->dispatcher->dispatchFor($reopened, $reopened->wasRecentlyCreated, $reopened->justRegressed);
    expect($event)->toBe('regression');
});

it('fires a spike rule once when the occurrence threshold is reached (#24)', function (): void {
    $this->project->alertRules()->create([
        'channel' => 'webhook',
        'target' => ['url' => 'https://hooks.test/spike'],
        'enabled' => true,
        'conditions' => ['threshold' => 5],
    ]);
    $issue = $this->store->upsert($this->project->id, 'spike', ['type' => 'E']);
    $issue->update(['occurrences_count' => 5]);

    // A recurring occurrence (not new/regression) whose count hits the threshold.
    $event = $this->dispatcher->dispatchFor($issue->fresh(), false, false);

    expect($event)->toBe('threshold');
    Http::assertSent(fn ($r) => $r->url() === 'https://hooks.test/spike');
});

it('does not fire a spike rule before the threshold (#24)', function (): void {
    $this->project->alertRules()->create([
        'channel' => 'webhook',
        'target' => ['url' => 'https://hooks.test/spike'],
        'enabled' => true,
        'conditions' => ['threshold' => 5],
    ]);
    $issue = $this->store->upsert($this->project->id, 'spike', ['type' => 'E']);
    $issue->update(['occurrences_count' => 3]);

    $event = $this->dispatcher->dispatchFor($issue->fresh(), false, false);
    expect($event)->toBeNull();
});
