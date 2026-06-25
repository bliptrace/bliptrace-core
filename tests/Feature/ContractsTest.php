<?php

declare(strict_types=1);

use G8Scope\Core\Contracts;

dataset('contracts', [
    Contracts\Fingerprinter::class,
    Contracts\Normalizer::class,
    Contracts\Scrubber::class,
    Contracts\IssueStore::class,
    Contracts\OccurrenceStore::class,
    Contracts\EndpointResolver::class,
    Contracts\EntitlementGate::class,
    Contracts\SupportProvider::class,
    Contracts\ReportGenerator::class,
    Contracts\Notifier::class,
]);

it('defines each driver seam as an interface', function (string $contract): void {
    expect(interface_exists($contract))->toBeTrue();
})->with('contracts');
