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
        'fingerprinter' => null,   // implements G8scope\Core\Contracts\Fingerprinter
        'scrubber' => null,        // implements G8scope\Core\Contracts\Scrubber
        'issue_store' => null,     // implements G8scope\Core\Contracts\IssueStore (Postgres)
        'occurrence_store' => null,// implements G8scope\Core\Contracts\OccurrenceStore (ClickHouse)
        'endpoint_resolver' => null,
        'entitlement_gate' => null,
        'support_provider' => null,
        'report_generator' => null,
        'notifier' => null,
    ],

    // Default retention in days for the shared tier (illustrative — set during pricing).
    'retention_days' => 14,
];
