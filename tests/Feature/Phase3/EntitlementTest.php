<?php

declare(strict_types=1);

use G8Scope\Core\Actions\InviteMember;
use G8Scope\Core\Actions\SwitchEndpoint;
use G8Scope\Core\Contracts\EndpointResolver;
use G8Scope\Core\Contracts\EntitlementGate;
use G8Scope\Core\Enums\ProjectRole;
use G8Scope\Core\Exceptions\EntitlementDeniedException;
use G8Scope\Core\Exceptions\SeatLimitException;
use G8Scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function account(array $entitlement = []): Account
{
    $account = Account::create(['name' => 'DevHub']);
    $account->entitlement()->create($entitlement);

    return $account;
}

it('denies custom capability on the default tier', function (): void {
    $account = account();
    expect(app(EntitlementGate::class)->allows($account->id, 'custom_api'))->toBeFalse();
});

it('allows custom_api when unlocked + flagged', function (): void {
    $account = account(['custom_api' => true, 'granted_at' => now()]);
    expect(app(EntitlementGate::class)->allows($account->id, 'custom_api'))->toBeTrue()
        ->and(app(EntitlementGate::class)->allows($account->id, 'custom_mcp'))->toBeFalse();
});

it('counts seats remaining (1 free owner seat)', function (): void {
    $account = account(['seats' => 2]);
    $project = $account->projects()->create(['name' => 'P']);
    $gate = app(EntitlementGate::class);

    expect($gate->seatsRemaining($account->id))->toBe(2);
    app(InviteMember::class)($project, 1001, ProjectRole::Owner);
    expect($gate->seatsRemaining($account->id))->toBe(1);
});

it('blocks invites when seats are exhausted', function (): void {
    $account = account(['seats' => 1]);
    $project = $account->projects()->create(['name' => 'P']);
    app(InviteMember::class)($project, 1001);

    expect(fn () => app(InviteMember::class)($project, 1002))
        ->toThrow(SeatLimitException::class);
});

it('switches a project to a dedicated endpoint only when entitled', function (): void {
    $account = account(['custom_api' => true, 'granted_at' => now()]);
    $project = $account->projects()->create(['name' => 'P']);
    $resolver = app(EndpointResolver::class);

    expect($resolver->resolve($project->id, 'api'))->toBe(config('g8scope.endpoints.api'));

    app(SwitchEndpoint::class)($project, 'api', 'https://dedicated.example/ingest');
    expect($resolver->resolve($project->fresh()->id, 'api'))->toBe('https://dedicated.example/ingest');
});

it('refuses to switch endpoint without entitlement', function (): void {
    $account = account(); // not unlocked
    $project = $account->projects()->create(['name' => 'P']);

    expect(fn () => app(SwitchEndpoint::class)($project, 'api', 'https://x/'))
        ->toThrow(EntitlementDeniedException::class);
});
