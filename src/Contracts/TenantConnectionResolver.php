<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Resolves which database connection an account's data lives on (spec §10, #40).
 * Gov tenants with the isolation entitlement get a dedicated connection (its
 * database/schema is provisioned at deploy time); everyone else uses the shared
 * default. Driver-based so the routing strategy can be swapped.
 */
interface TenantConnectionResolver
{
    /** Connection name for the account, or the framework default when shared. */
    public function connectionFor(int $accountId): string;
}
