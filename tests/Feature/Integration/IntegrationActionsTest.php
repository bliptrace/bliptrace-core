<?php

declare(strict_types=1);

use G8Scope\Core\Actions\ArchiveProject;
use G8Scope\Core\Actions\CreateProject;
use G8Scope\Core\Actions\IssueIngestKey;
use G8Scope\Core\Actions\PurgeProject;
use G8Scope\Core\Actions\RecordDeploy;
use G8Scope\Core\Actions\RevokeIngestKey;
use G8Scope\Core\Models\Account;
use G8Scope\Core\Models\IngestKey;
use G8Scope\Core\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    expect($plain)->toStartWith('blip_')
        ->and($key->prefix)->toBe('blip_')
        ->and($key->fresh()->prefix)->toBe(IssueIngestKey::PREFIX)
        ->and($key->key_hash)->toBe(hash('sha256', $plain))
        ->and($key->last_four)->toBe(substr($plain, -4))
        ->and($key->toArray())->not->toHaveKey('key_hash');

    app(RevokeIngestKey::class)($key);
    $revokedAt = $key->fresh()->revoked_at;
    $this->travel(5)->minutes();
    app(RevokeIngestKey::class)($key->fresh());

    expect($key->fresh()->revoked_at->equalTo($revokedAt))->toBeTrue();
});

it('keeps the legacy g8s_ prefix on keys stored without an explicit prefix', function (): void {
    [$project] = app(CreateProject::class)(integrationAccount(), 'p');

    // A row written before the prefix column existed: the migration default backfills it.
    $legacy = IngestKey::create([
        'project_id' => $project->id,
        'name' => 'legacy',
        'key_hash' => hash('sha256', 'g8s_legacy'),
        'last_four' => 'gacy',
    ]);

    expect($legacy->fresh()->prefix)->toBe(IssueIngestKey::LEGACY_PREFIX)->toBe('g8s_');
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

it('purges an archived project and every orphan-prone row, leaving other projects alone', function (): void {
    $account = integrationAccount();
    [$doomed] = app(CreateProject::class)($account, 'doomed');
    [$kept] = app(CreateProject::class)($account, 'kept');

    foreach ([$doomed, $kept] as $project) {
        $issue = $project->issues()->create(['fingerprint' => 'fp-'.$project->id, 'type' => 'RuntimeException', 'level' => 'error', 'first_seen_at' => now(), 'last_seen_at' => now()]);

        foreach (range(1, 3) as $i) {
            DB::table('occurrences')->insert([
                'event_id' => (string) Str::uuid(), 'project_id' => $project->id, 'issue_uuid' => $issue->uuid,
                'fingerprint' => 'fp', 'level' => 'error', 'occurred_at' => now()->subDays($i), 'payload' => '{}',
            ]);
        }
        $row = ['uuid' => (string) Str::uuid(), 'project_id' => $project->id];
        DB::table('web_vitals')->insert($row + ['metric' => 'lcp', 'value' => 1.0, 'occurred_at' => now()]);
        DB::table('transactions')->insert($row + ['name' => 'GET /', 'duration_ms' => 5, 'occurred_at' => now()]);
        DB::table('metric_points')->insert($row + ['name' => 'm', 'value' => 1.0, 'recorded_at' => now()]);
    }

    app(ArchiveProject::class)($doomed);
    app(PurgeProject::class)($doomed, chunk: 2);

    expect(Project::withTrashed()->find($doomed->id))->toBeNull();

    foreach (['occurrences' => 3, 'web_vitals' => 1, 'transactions' => 1, 'metric_points' => 1, 'issues' => 1] as $table => $keptCount) {
        expect(DB::table($table)->where('project_id', $doomed->id)->count())->toBe(0, $table)
            ->and(DB::table($table)->where('project_id', $kept->id)->count())->toBe($keptCount, $table);
    }
});
