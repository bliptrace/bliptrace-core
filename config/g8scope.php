<?php

declare(strict_types=1);
use G8Scope\Core\Billing\StripeBillingProvider;

/*
 * bliptrace-core configuration.
 *
 * Driver-based seams: each contract resolves to a concrete implementation. The
 * web app binds production drivers; tests bind array/stub drivers. See spec §18.
 */
return [
    'drivers' => [
        'fingerprinter' => null,   // implements G8Scope\Core\Contracts\Fingerprinter
        'scrubber' => null,        // implements G8Scope\Core\Contracts\Scrubber
        'issue_store' => null,     // implements G8Scope\Core\Contracts\IssueStore (Postgres)
        'occurrence_store' => null, // implements G8Scope\Core\Contracts\OccurrenceStore (Postgres)
        'endpoint_resolver' => null,
        'entitlement_gate' => null,
        // BillingProvider: 'stripe' selects the Stripe one-time Checkout driver;
        // anything else (or unset) falls back to ManualBillingProvider (admin unlock).
        'billing_provider' => env('G8SCOPE_BILLING_DRIVER') === 'stripe'
            ? StripeBillingProvider::class
            : null,
        'apm_store' => null,        // implements G8Scope\Core\Contracts\ApmStore
        'metric_store' => null,     // implements G8Scope\Core\Contracts\MetricStore
        'rum_store' => null,        // implements G8Scope\Core\Contracts\RumStore
        'tenant_connection_resolver' => null, // implements G8Scope\Core\Contracts\TenantConnectionResolver
        'support_provider' => null,
        'report_generator' => null,
        'notifier' => null,
    ],

    // Default retention in days for the shared tier (illustrative — set during pricing).
    'retention_days' => 14,

    // Free seats granted to a new account (owner is always free on top of this).
    // The web app overrides this from a DB setting so admins can change the default.
    'default_free_seats' => (int) env('G8SCOPE_DEFAULT_FREE_SEATS', 3),

    // Default (shared) endpoints provisioned for every new project (Project Observer).
    'endpoints' => [
        'api' => env('G8SCOPE_API_URL', 'https://ingest.scope.g8suite.com'),
        'mcp' => env('G8SCOPE_MCP_URL', 'https://mcp.scope.g8suite.com'),
    ],

    // Billing (BillingProvider driver) — spec §12–13. One-time, permanent
    // capability unlock via Stripe Checkout (mode=payment). International-first:
    // currency/locale are driven by the Stripe Price + Checkout, not configured here.
    'billing' => [
        'stripe' => [
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            // One-time Stripe Price IDs per purchasable capability. A null/empty
            // price means that capability is unlocked manually (no online checkout).
            'prices' => [
                'custom_api' => env('STRIPE_PRICE_CUSTOM_API'),
                'custom_mcp' => env('STRIPE_PRICE_CUSTOM_MCP'),
                'seats' => env('STRIPE_PRICE_SEAT'),
            ],
        ],
        // Where Stripe returns the customer after checkout. `{CHECKOUT_SESSION_ID}`
        // in the success URL is substituted by Stripe.
        'success_url' => env('G8SCOPE_BILLING_SUCCESS_URL'),
        'cancel_url' => env('G8SCOPE_BILLING_CANCEL_URL'),
        // Stripe Tax (automatic_tax) on checkout. Requires Stripe Tax activated +
        // tax registrations in the Stripe account. Off by default.
        'tax_enabled' => (bool) env('G8SCOPE_BILLING_TAX', false),
    ],

    // g8desk support integration (SupportProvider driver) — spec §15.
    'g8desk' => [
        'base_url' => env('G8SCOPE_G8DESK_URL', ''),
        'token' => env('G8SCOPE_G8DESK_TOKEN'),
        'organization' => env('G8SCOPE_G8DESK_ORG'),
        'product' => env('G8SCOPE_G8DESK_PRODUCT'),
    ],

    // Occurrences live in Postgres, partitioned monthly. g8scope:occurrences:maintain
    // (scheduled daily) drops partitions older than this; the longest entitlement
    // retention_days wins if higher. 14 = spec §13 shared-tier figure (illustrative).
    'occurrences' => [
        'retention_days' => (int) env('G8SCOPE_OCCURRENCES_RETENTION_DAYS', 14),
    ],
];
