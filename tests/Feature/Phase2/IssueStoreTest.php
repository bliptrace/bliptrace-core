<?php

declare(strict_types=1);

use G8scope\Core\Contracts\IssueStore;
use G8scope\Core\Enums\IssueStatus;
use G8scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->store = app(IssueStore::class);
    $account = Account::create(['name' => 'DevHub']);
    $this->project = $account->projects()->create(['name' => 'P']);
});

it('creates then increments on repeat fingerprints', function (): void {
    $first = ($this->store)->upsert($this->project->id, 'fp1', ['type' => 'E']);
    expect($first->wasRecentlyCreated)->toBeTrue()
        ->and($first->occurrences_count)->toBe(1);

    $second = ($this->store)->upsert($this->project->id, 'fp1', ['type' => 'E']);
    expect($second->wasRecentlyCreated)->toBeFalse()
        ->and($second->occurrences_count)->toBe(2)
        ->and($second->id)->toBe($first->id);
});

it('reopens a resolved issue and flags regression', function (): void {
    $issue = ($this->store)->upsert($this->project->id, 'fp2', ['type' => 'E']);
    ($this->store)->setStatus($issue->uuid, IssueStatus::Resolved);

    $reopened = ($this->store)->upsert($this->project->id, 'fp2', ['type' => 'E']);
    expect($reopened->status)->toBe(IssueStatus::Open)
        ->and($reopened->justRegressed)->toBeTrue()
        ->and($reopened->resolved_at)->toBeNull();
});

it('assigns an issue to a user', function (): void {
    $issue = ($this->store)->upsert($this->project->id, 'fp3', ['type' => 'E']);
    ($this->store)->assign($issue->uuid, 77);
    expect($issue->fresh()->assignee_user_id)->toBe(77);
});
