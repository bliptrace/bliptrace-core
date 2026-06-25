<?php

declare(strict_types=1);

namespace G8Scope\Core\Isolation;

use G8Scope\Core\Contracts\TenantConnectionResolver;
use G8Scope\Core\Models\Entitlement;

/**
 * Default isolation resolver (spec §10, §18). Returns the account entitlement's
 * dedicated `isolation_connection` when the entitlement is unlocked and a
 * connection is set; otherwise the shared framework-default connection. The
 * dedicated connection itself is provisioned at deploy time (config/database.php).
 */
class DefaultTenantConnectionResolver implements TenantConnectionResolver
{
    public function connectionFor(int $accountId): string
    {
        $default = (string) config('database.default');

        $entitlement = Entitlement::where('account_id', $accountId)->first();
        if ($entitlement === null || ! $entitlement->unlocked()) {
            return $default;
        }

        $connection = (string) ($entitlement->isolation_connection ?? '');
        if ($connection === '' || config("database.connections.{$connection}") === null) {
            return $default;
        }

        return $connection;
    }
}
