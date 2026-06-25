<?php

declare(strict_types=1);

use G8Scope\Core\Actions\OpenIncident;
use G8Scope\Core\Actions\ResolveIncident;
use G8Scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('opens an incident idempotently and resolves with MTTR', function (): void {
    $account = Account::create(['name' => 'DevHub']);
    $project = $account->projects()->create(['name' => 'P']);
    $issue = $project->issues()->create(['fingerprint' => 'fp', 'type' => 'E']);

    Carbon::setTestNow('2026-06-25 10:00:00');
    $incident = app(OpenIncident::class)($issue);
    $again = app(OpenIncident::class)($issue);
    expect($again->id)->toBe($incident->id); // idempotent

    Carbon::setTestNow('2026-06-25 10:05:00');
    $resolved = app(ResolveIncident::class)($incident);

    expect($resolved->status)->toBe('resolved')
        ->and($resolved->mttr_seconds)->toBe(300);

    Carbon::setTestNow();
});
