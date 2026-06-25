<?php

declare(strict_types=1);

use G8scope\Core\Alerting\AlertDispatcher;
use G8scope\Core\Contracts\IssueStore;
use G8scope\Core\Contracts\Notifier;
use G8scope\Core\Enums\IssueStatus;
use G8scope\Core\Models\Account;
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
