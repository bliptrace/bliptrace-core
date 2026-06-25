<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\Entitlement;

/**
 * Grant a purchased capability to an account (spec §12–13). The entitlement is a
 * one-time, permanent unlock, so this sets `granted_at` once and enables the
 * specific capability flag (or adds seats). Single-sourced grant used by both the
 * BillingProvider webhook and the admin override, so the two never drift.
 *
 * Idempotent for boolean capabilities (re-running keeps the flag true); for
 * `seats`, callers must guarantee at-most-once delivery (the billing ledger does).
 */
class GrantCapability
{
    /**
     * @param  'custom_api'|'custom_mcp'|'seats'  $capability
     */
    public function __invoke(int $accountId, string $capability, int $quantity = 1): Entitlement
    {
        $entitlement = Entitlement::firstOrNew(['account_id' => $accountId]);

        if ($entitlement->granted_at === null) {
            $entitlement->granted_at = now();
        }

        match ($capability) {
            'custom_api' => $entitlement->custom_api = true,
            'custom_mcp' => $entitlement->custom_mcp = true,
            // 'seats' — the remaining capability; default keeps the match exhaustive.
            default => $entitlement->seats = (int) ($entitlement->seats ?? 1) + max(1, $quantity),
        };

        $entitlement->save();

        return $entitlement;
    }
}
