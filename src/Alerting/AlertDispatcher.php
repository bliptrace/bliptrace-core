<?php

declare(strict_types=1);

namespace G8Scope\Core\Alerting;

use G8Scope\Core\Contracts\Notifier;
use G8Scope\Core\Events\AlertDispatched;
use G8Scope\Core\Models\Issue;

/**
 * Decides when to alert (spec §7). Smart grouping is structural: a rule with no
 * threshold fires only on a NEW issue or a REGRESSION — so 1000 occurrences of an
 * already-open issue produce zero extra alerts. A rule carrying a `threshold`
 * condition is a spike alert (P2): it fires once, when the issue's occurrence
 * count reaches that threshold, instead of on new/regression.
 */
class AlertDispatcher
{
    public function __construct(private readonly Notifier $notifier) {}

    public function dispatchFor(Issue $issue, bool $isNew, bool $regressed): ?string
    {
        $rules = $issue->project->alertRules()->where('enabled', true)->get();
        if ($rules->isEmpty()) {
            return null;
        }

        $firedEvent = null;
        $count = 0;

        foreach ($rules as $rule) {
            $threshold = is_array($rule->conditions) ? ($rule->conditions['threshold'] ?? null) : null;

            if ($threshold !== null) {
                // Spike rule: fire once, at the occurrence that reaches the threshold.
                if ((int) $issue->occurrences_count !== (int) $threshold) {
                    continue;
                }
                $event = 'threshold';
            } else {
                // Lifecycle rule: new issue or regression only (smart grouping).
                if (! $isNew && ! $regressed) {
                    continue;
                }
                $event = $isNew ? 'new_issue' : 'regression';
            }

            $this->notifier->notify($rule->channel, [
                'event' => $event,
                'message' => $this->message($event, $issue),
                'target' => $rule->target,
                'issue' => ['uuid' => $issue->uuid, 'type' => $issue->type],
            ]);

            $firedEvent ??= $event;
            $count++;
        }

        if ($firedEvent === null) {
            return null;
        }

        AlertDispatched::dispatch($issue, $firedEvent, $count);

        return $firedEvent;
    }

    private function message(string $event, Issue $issue): string
    {
        $label = match ($event) {
            'new_issue' => 'New issue',
            'regression' => 'Regression',
            'threshold' => sprintf('Spike (%d occurrences)', (int) $issue->occurrences_count),
            default => ucfirst($event),
        };

        return sprintf('[%s] %s: %s (%s)', $label, $issue->type, (string) $issue->message, $issue->uuid);
    }
}
