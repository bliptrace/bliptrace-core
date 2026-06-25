<?php

declare(strict_types=1);

namespace G8scope\Core\Alerting;

use G8scope\Core\Contracts\Notifier;
use G8scope\Core\Events\AlertDispatched;
use G8scope\Core\Models\Issue;

/**
 * Decides when to alert (spec §7). Smart grouping is structural: an alert fires
 * only on a NEW issue or a REGRESSION — so 1000 occurrences of an already-open
 * issue produce zero extra alerts. Threshold/spike alerts are P2.
 */
class AlertDispatcher
{
    public function __construct(private readonly Notifier $notifier) {}

    public function dispatchFor(Issue $issue, bool $isNew, bool $regressed): ?string
    {
        if (! $isNew && ! $regressed) {
            return null; // smart grouping: recurring occurrence, no new alert
        }

        $event = $isNew ? 'new_issue' : 'regression';
        $message = sprintf(
            '[%s] %s: %s (%s)',
            $event === 'new_issue' ? 'New issue' : 'Regression',
            $issue->type,
            (string) $issue->message,
            $issue->uuid,
        );

        $rules = $issue->project->alertRules()->where('enabled', true)->get();
        $count = 0;
        foreach ($rules as $rule) {
            $this->notifier->notify($rule->channel, [
                'event' => $event,
                'message' => $message,
                'target' => $rule->target,
                'issue' => ['uuid' => $issue->uuid, 'type' => $issue->type],
            ]);
            $count++;
        }

        AlertDispatched::dispatch($issue, $event, $count);

        return $event;
    }
}
