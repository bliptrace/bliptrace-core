<?php

declare(strict_types=1);

namespace G8Scope\Core\Notifications;

use G8Scope\Core\Contracts\Notifier;
use InvalidArgumentException;

/**
 * Multi-channel alert dispatch (spec §7): email / Slack / Telegram / webhook.
 * Channels are pluggable via the constructor map.
 */
class ChannelNotifier implements Notifier
{
    /** @param array<string,NotificationChannel> $channels */
    public function __construct(private array $channels = []) {}

    public function register(string $name, NotificationChannel $channel): void
    {
        $this->channels[$name] = $channel;
    }

    public function notify(string $channel, array $payload): void
    {
        if (! isset($this->channels[$channel])) {
            throw new InvalidArgumentException("Unknown notification channel: {$channel}");
        }

        $this->channels[$channel]->send($payload);
    }
}
