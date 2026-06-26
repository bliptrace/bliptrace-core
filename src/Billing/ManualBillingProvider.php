<?php

declare(strict_types=1);

namespace G8Scope\Core\Billing;

use G8Scope\Core\Contracts\BillingProvider;

/**
 * Default billing driver: no automated checkout. Capabilities are unlocked
 * manually by an administrator (Admin > Settings > Entitlements). A real
 * provider (Stripe / Billplz / Chip / …) plugs in via config('g8scope.drivers.billing_provider').
 */
class ManualBillingProvider implements BillingProvider
{
    public function checkoutUrl(int $accountId, string $capability, int $quantity = 1): ?string
    {
        return null;
    }
}
