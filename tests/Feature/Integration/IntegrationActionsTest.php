<?php

declare(strict_types=1);

use G8Scope\Core\Actions\ArchiveProject;
use G8Scope\Core\Actions\CreateProject;
use G8Scope\Core\Actions\IssueIngestKey;
use G8Scope\Core\Actions\RecordDeploy;
use G8Scope\Core\Actions\RevokeIngestKey;
use G8Scope\Core\Models\Account;
use G8Scope\Core\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function integrationAccount(): Account
{
    $account = Account::create(['name' => 'DevHub']);
    $account->entitlement()->create([]);

    return $account;
}

it('creates a project idempotently on external_ref and drops the unrevealable key', function (): void {
    $account = integrationAccount();

    [$first, $created] = app(CreateProject::class)($account, 'shop-api · production', 'g8deck:workload:1', 'production');
    [$again, $createdAgain] = app(CreateProject::class)($account, 'renamed', 'g8deck:workload:1');

    expect($created)->toBeTrue()
        ->and($createdAgain)->toBeFalse()
        ->and($again->is($first))->toBeTrue()
        ->and($first->slug)->toBe('shop-api-production')
        ->and($first->environment)->toBe('production')
        ->and($first->ingestKeys()->count())->toBe(0);
});

it('scopes external_ref to the account', function (): void {
    [$a] = app(CreateProject::class)(integrationAccount(), 'a', 'ref');
    [$b, $created] = app(CreateProject::class)(integrationAccount(), 'b', 'ref');

    expect($created)->toBeTrue()->and($b->is($a))->toBeFalse();
});

it('issues a prefixed key once, stores only its hash, and revokes it', function (): void {
    [$project] = app(CreateProject::class)(integrationAccount(), 'p');

    [$key, $plain] = app(IssueIngestKey::class)($project, 'g8deck');

    expect($plain)->toStartWith('g8s_')
        ->and($key->key_hash)->toBe(hash('sha256', $plain))
        ->and($key->last_four)->toBe(substr($plain, -4))
        ->and($key->toArray())->not->toHaveKey('key_hash');

    app(RevokeIngestKey::class)($key);
    $revokedAt = $key->fresh()->revoked_at;
    $this->travel(5)->minutes();
    app(RevokeIngestKey::class)($key->fresh());

    expect($key->fresh()->revoked_at->equalTo($revokedAt))->toBeTrue();
});

it('archives a project, revokes its keys and frees the external_ref', function (): void {
    $account = integrationAccount();
    [$project] = app(CreateProject::class)($account, 'p', 'ref');
    app(IssueIngestKey::class)($project, 'k');

    app(ArchiveProject::class)($project);

    expect(Project::find($project->id))->toBeNull()
        ->and(Project::withTrashed()->find($project->id))->not->toBeNull()
        ->and($project->ingestKeys()->whereNull('revoked_at')->count())->toBe(0);

    [$fresh, $created] = app(CreateProject::class)($account, 'p', 'ref');
    expect($created)->toBeTrue()->and($fresh->id)->not->toBe($project->id);
});

it('records a deploy marker', function (): void {
    [$project] = app(CreateProject::class)(integrationAccount(), 'p');

    $deploy = app(RecordDeploy::class)($project, ['release' => '1.2.3', 'commit_sha' => 'abc123']);

    expect($deploy->uuid)->not->toBeNull()
        ->and($deploy->deployed_at)->not->toBeNull()
        ->and($project->deploys()->count())->toBe(1);
});
