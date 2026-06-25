<?php

declare(strict_types=1);

use G8Scope\Core\Analytics\ReleaseHealth;
use G8Scope\Core\Models\Account;
use G8Scope\Core\Stores\ClickHouseOccurrenceStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('compares two releases: new issues + occurrence delta', function (): void {
    $store = app(ClickHouseOccurrenceStore::class);
    $store->migrate();

    $account = Account::create(['name' => 'DevHub']);
    $project = $account->projects()->create(['name' => 'P']);
    $rel1 = 'app@'.Str::random(6);
    $rel2 = 'app@'.Str::random(6);

    // shared issue in both releases + a new one only in after
    $project->issues()->create(['fingerprint' => 'shared', 'type' => 'E', 'release' => $rel1]);
    $project->issues()->create(['fingerprint' => 'shared', 'type' => 'E', 'release' => $rel2, 'fingerprint' => 'shared2']);
    $project->issues()->create(['fingerprint' => 'newfp', 'type' => 'E', 'release' => $rel2]);

    foreach (['a', 'b'] as $e) {
        $store->record(['project_id' => $project->id, 'issue_uuid' => Str::uuid(), 'release' => $rel1, 'event_id' => $e]);
    }
    foreach (['c', 'd', 'e'] as $e) {
        $store->record(['project_id' => $project->id, 'issue_uuid' => Str::uuid(), 'release' => $rel2, 'event_id' => $e]);
    }

    $health = app(ReleaseHealth::class)->compare($project->id, $rel1, $rel2);

    expect($health['before']['occurrences'])->toBe(2)
        ->and($health['after']['occurrences'])->toBe(3)
        ->and($health['occurrence_delta'])->toBe(1)
        ->and($health['new_issues'])->toBeGreaterThanOrEqual(1)
        ->and($health['regressed'])->toBeTrue();
});
