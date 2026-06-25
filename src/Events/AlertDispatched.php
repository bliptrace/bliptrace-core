<?php

declare(strict_types=1);

namespace G8Scope\Core\Events;

use G8Scope\Core\Models\Issue;
use Illuminate\Foundation\Events\Dispatchable;

class AlertDispatched
{
    use Dispatchable;

    public function __construct(
        public readonly Issue $issue,
        public readonly string $event, // new_issue | regression
        public readonly int $rulesNotified,
    ) {}
}
