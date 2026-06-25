<?php

declare(strict_types=1);

use G8scope\Core\Enums\IssueStatus;
use G8scope\Core\Enums\Level;
use G8scope\Core\Enums\ProjectRole;
use G8scope\Core\Models\Account;
use G8scope\Core\Models\Entitlement;
use G8scope\Core\Models\IngestKey;
use G8scope\Core\Models\Issue;
use G8scope\Core\Models\Membership;
use G8scope\Core\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

function makeProject(): Project
{
    $account = Account::create(['name' => 'DevHub']);
    $account->entitlement()->create([]);

    return $account->projects()->create(['name' => 'OSC Portal']);
}

it('wires the account → project aggregate with dual keys', function (): void {
    $project = makeProject();

    expect($project->uuid)->not->toBeNull()
        ->and($project->account)->toBeInstanceOf(Account::class)
        ->and($project->account->entitlement)->toBeInstanceOf(Entitlement::class)
        ->and($project->account->entitlement->seats)->toBe(1)
        ->and($project->account->entitlement->max_projects)->toBeNull()
        ->and($project->account->entitlement->unlocked())->toBeFalse();
});

it('casts enums on issues and memberships', function (): void {
    $project = makeProject();

    $member = Membership::create([
        'project_id' => $project->id,
        'user_id' => 999,
        'role' => ProjectRole::Owner,
    ]);

    $issue = $project->issues()->create([
        'fingerprint' => 'abc123',
        'type' => 'RuntimeException',
        'level' => Level::Error,
        'status' => IssueStatus::Open,
    ]);

    expect($member->role)->toBe(ProjectRole::Owner)
        ->and($issue->level)->toBe(Level::Error)
        ->and($issue->status)->toBe(IssueStatus::Open)
        ->and($project->issues()->count())->toBe(1);
});

it('enforces the project+fingerprint uniqueness', function (): void {
    $project = makeProject();
    $project->issues()->create(['fingerprint' => 'dup', 'type' => 'E']);

    // Nested transaction = savepoint, so the unique violation rolls back to the
    // savepoint without poisoning the RefreshDatabase outer transaction (Postgres).
    expect(fn () => DB::transaction(
        fn () => $project->issues()->create(['fingerprint' => 'dup', 'type' => 'E'])
    ))->toThrow(QueryException::class);
});

it('hides the ingest key hash', function (): void {
    $project = makeProject();
    $key = IngestKey::create([
        'project_id' => $project->id,
        'key_hash' => hash('sha256', 'secret'),
        'last_four' => 'cret',
    ]);

    expect($key->toArray())->not->toHaveKey('key_hash')
        ->and($key->isActive())->toBeTrue();
});

it('audits issue status changes (owen-it)', function (): void {
    $project = makeProject();
    $issue = $project->issues()->create([
        'fingerprint' => 'aud', 'type' => 'E', 'status' => IssueStatus::Open,
    ]);

    $issue->update(['status' => IssueStatus::Resolved]);

    $audit = Audit::where('auditable_type', Issue::class)
        ->where('auditable_id', $issue->id)
        ->where('event', 'updated')
        ->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->new_values['status'] ?? null)->toBe(IssueStatus::Resolved->value);
});
