<?php

declare(strict_types=1);

namespace G8scope\Core\Contracts;

/**
 * Multi-channel alert dispatch (spec §7, §18): email / Slack / Telegram / webhook.
 * Smart grouping (1000 errors → 1 alert) is applied before dispatch.
 */
interface Notifier
{
    /** @param array<string,mixed> $payload */
    public function notify(string $channel, array $payload): void;
}
