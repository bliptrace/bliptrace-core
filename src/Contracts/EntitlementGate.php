<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Account-level entitlement gate (spec §13). Checks permanent capability flags
 * (custom_api / custom_mcp) and caps before a project may bind a dedicated endpoint.
 */
interface EntitlementGate
{
    /** @param 'custom_api'|'custom_mcp' $capability */
    public function allows(int $accountId, string $capability): bool;

    /** Remaining seats for the account (1 free owner seat by default). */
    public function seatsRemaining(int $accountId): int;
}
