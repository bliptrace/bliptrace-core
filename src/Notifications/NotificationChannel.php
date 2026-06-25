<?php

declare(strict_types=1);

namespace G8Scope\Core\Notifications;

interface NotificationChannel
{
    /** @param array<string,mixed> $payload */
    public function send(array $payload): void;
}
