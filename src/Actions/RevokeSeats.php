<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\Entitlement;

/**
 * Take back seats when their Stripe subscription ends (ADR-002). The inverse of
 * GrantCapability's `seats` branch: the owner's seat is never removed, so the
 * count floors at 1. Callers guarantee at-most-once delivery (the billing ledger does).
 */
class RevokeSeats
{
    public function __invoke(int $accountId, int $quantity): ?Entitlement
    {
        $entitlement = Entitlement::where('account_id', $accountId)->first();

        if ($entitlement === null) {
            return null;
        }

        $entitlement->seats = max(1, (int) $entitlement->seats - max(1, $quantity));
        $entitlement->save();

        return $entitlement;
    }
}
