<?php

declare(strict_types=1);

it('registers and merges config', function (): void {
    expect(config('g8scope'))->toBeArray()
        ->and(config('g8scope.retention_days'))->toBe(14)
        ->and(config('g8scope.drivers'))->toHaveKeys([
            'fingerprinter', 'scrubber', 'issue_store', 'occurrence_store',
            'endpoint_resolver', 'entitlement_gate', 'support_provider',
            'report_generator', 'notifier',
        ]);
});
