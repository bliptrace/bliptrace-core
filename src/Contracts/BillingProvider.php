<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Pluggable billing backend (spec §12–13). Entitlement is a one-time, permanent
 * unlock — so this returns a checkout URL to purchase a capability, or null when
 * billing is handled manually (the default). On a successful purchase the provider
 * grants the account Entitlement (the same flags the admin grant sets).
 */
interface BillingProvider
{
    /**
     * Checkout URL to purchase a capability for an account, or null when billing
     * is manual / no provider is configured.
     *
     * @param  'custom_api'|'custom_mcp'|'seats'  $capability
     */
    public function checkoutUrl(int $accountId, string $capability): ?string;
}
