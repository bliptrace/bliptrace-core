<?php

declare(strict_types=1);

/*
 * g8scope-core configuration.
 *
 * Driver-based seams: each contract resolves to a concrete implementation. The
 * web app binds production drivers; tests bind array/stub drivers. See spec §18.
 */
return [
    'drivers' => [
        'fingerprinter' => null,   // implements G8Scope\Core\Contracts\Fingerprinter
        'scrubber' => null,        // implements G8Scope\Core\Contracts\Scrubber
        'issue_store' => null,     // implements G8Scope\Core\Contracts\IssueStore (Postgres)
        'occurrence_store' => null, // implements G8Scope\Core\Contracts\OccurrenceStore (ClickHouse)
        'endpoint_resolver' => null,
        'entitlement_gate' => null,
        'billing_provider' => null, // implements G8Scope\Core\Contracts\BillingProvider
        'apm_store' => null,        // implements G8Scope\Core\Contracts\ApmStore
        'support_provider' => null,
        'report_generator' => null,
        'notifier' => null,
    ],

    // Default retention in days for the shared tier (illustrative — set during pricing).
    'retention_days' => 14,

    // Default (shared) endpoints provisioned for every new project (Project Observer).
    'endpoints' => [
        'api' => env('G8SCOPE_API_URL', 'https://ingest.scope.g8suite.com'),
        'mcp' => env('G8SCOPE_MCP_URL', 'https://mcp.scope.g8suite.com'),
    ],

    // g8desk support integration (SupportProvider driver) — spec §15.
    'g8desk' => [
        'base_url' => env('G8SCOPE_G8DESK_URL', ''),
        'token' => env('G8SCOPE_G8DESK_TOKEN'),
        'organization' => env('G8SCOPE_G8DESK_ORG'),
        'product' => env('G8SCOPE_G8DESK_PRODUCT'),
    ],

    // ClickHouse connection for the OccurrenceStore driver (occurrences live in CH).
    'clickhouse' => [
        'host' => env('G8SCOPE_CH_HOST', '127.0.0.1'),
        'port' => (int) env('G8SCOPE_CH_PORT', 8123),
        'username' => env('G8SCOPE_CH_USER', 'default'),
        'password' => env('G8SCOPE_CH_PASS', ''),
        'database' => env('G8SCOPE_CH_DB', 'g8scope'),
    ],
];
