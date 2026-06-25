<?php

declare(strict_types=1);

namespace G8Scope\Core\Notifications\Channels;

use G8Scope\Core\Notifications\NotificationChannel;
use Illuminate\Support\Facades\Http;

class SlackChannel implements NotificationChannel
{
    public function send(array $payload): void
    {
        $url = $payload['target']['webhook'] ?? $payload['target'] ?? null;
        if (! is_string($url) || $url === '') {
            return;
        }

        Http::asJson()->post($url, ['text' => $payload['message'] ?? 'g8scope alert']);
    }
}
