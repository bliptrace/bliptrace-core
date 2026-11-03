<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use Carbon\CarbonImmutable;
use G8Scope\Core\Alerting\AlertDispatcher;
use G8Scope\Core\Contracts\Fingerprinter;
use G8Scope\Core\Contracts\IssueStore;
use G8Scope\Core\Contracts\OccurrenceWriter;
use G8Scope\Core\Contracts\Scrubber;
use G8Scope\Core\Models\Issue;

/**
 * Invokable Action: scrub → fingerprint → upsert issue (PG) → record occurrence
 * (CH) → dispatch alerts. This is the building block the app uses and the Go api
 * mirrors. Keeps the whole capture pipeline single-sourced in core.
 */
class RecordOccurrence
{
    public function __construct(
        private readonly Scrubber $scrubber,
        private readonly Fingerprinter $fingerprinter,
        private readonly IssueStore $issues,
        private readonly OccurrenceWriter $occurrences,
        private readonly AlertDispatcher $alerts,
    ) {}

    /** @param array<string,mixed> $envelope */
    public function __invoke(int $projectId, array $envelope): Issue
    {
        $envelope = $this->scrubber->scrub($envelope);
        $fingerprint = $this->fingerprinter->fingerprint($projectId, $envelope);

        $exception = $envelope['exception'] ?? [];
        $issue = $this->issues->upsert($projectId, $fingerprint, [
            'type' => $exception['type'] ?? 'Error',
            'message' => $exception['message'] ?? null,
            'level' => $envelope['level'] ?? 'error',
            'environment' => $envelope['environment'] ?? null,
            'release' => $envelope['release'] ?? null,
            'occurred_at' => $envelope['occurred_at'] ?? null,
        ]);

        $this->occurrences->record([
            'project_id' => $projectId,
            'issue_uuid' => $issue->uuid,
            'fingerprint' => $fingerprint,
            'event_id' => $envelope['event_id'] ?? '',
            'level' => $envelope['level'] ?? 'error',
            'environment' => $envelope['environment'] ?? '',
            'release' => $envelope['release'] ?? '',
            'message' => $exception['message'] ?? '',
            'occurred_at' => CarbonImmutable::parse($envelope['occurred_at'] ?? 'now')->utc()->format('Y-m-d H:i:s.v\\Z'),
            'payload' => $envelope,
        ]);

        $this->alerts->dispatchFor(
            $issue,
            $issue->wasRecentlyCreated,
            $issue->justRegressed,
        );

        return $issue;
    }
}
