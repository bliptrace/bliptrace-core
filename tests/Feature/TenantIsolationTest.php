<?php

declare(strict_types=1);

use G8Scope\Core\Contracts\TenantConnectionResolver;
use G8Scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // A deploy-provisioned dedicated connection (here, an alias of the default).
    config(['database.connections.gov_tenant' => config('database.connections.'.config('database.default'))]);
});

it('routes an isolated, entitled account to its dedicated connection', function (): void {
    $account = Account::create(['name' => 'Gov']);
    $account->entitlement()->create(['granted_at' => now(), 'isolation_connection' => 'gov_tenant']);

    expect(app(TenantConnectionResolver::class)->connectionFor($account->id))->toBe('gov_tenant');
});

it('falls back to the shared default when not isolated', function (): void {
    $account = Account::create(['name' => 'Shared']);
    $account->entitlement()->create(['granted_at' => now()]);

    expect(app(TenantConnectionResolver::class)->connectionFor($account->id))->toBe(config('database.default'));
});

it('ignores isolation when the entitlement is locked', function (): void {
    $account = Account::create(['name' => 'Locked']);
    $account->entitlement()->create(['isolation_connection' => 'gov_tenant']); // granted_at null

    expect(app(TenantConnectionResolver::class)->connectionFor($account->id))->toBe(config('database.default'));
});
