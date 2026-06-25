<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Contracts\Scrubber;
use G8Scope\Core\Contracts\SupportProvider;
use G8Scope\Core\Models\Issue;
use G8Scope\Core\Models\SupportLink;

/**
 * Request support for an issue (spec §15): scrub the issue context, attach it to a
 * g8desk ticket via the SupportProvider, and store the SupportLink
 * (issue ↔ DESK-YYYY-NNNNN). PII is scrubbed BEFORE it crosses the product boundary.
 */
class RequestSupport
{
    public function __construct(
        private readonly Scrubber $scrubber,
        private readonly SupportProvider $support,
    ) {}

    /** @param array<string,mixed> $context */
    public function __invoke(Issue $issue, array $context = []): SupportLink
    {
        $scrubbed = $this->scrubber->scrub($context + [
            'issue' => [
                'uuid' => $issue->uuid,
                'type' => $issue->type,
                'message' => $issue->message,
                'fingerprint' => $issue->fingerprint,
            ],
        ]);

        $reference = $this->support->attach($issue->uuid, $scrubbed);

        return SupportLink::create([
            'project_id' => $issue->project_id,
            'issue_id' => $issue->id,
            'reference' => $reference,
            'status' => 'open',
        ]);
    }
}
