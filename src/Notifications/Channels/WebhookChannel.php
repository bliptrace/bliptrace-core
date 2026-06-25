<?php

declare(strict_types=1);

namespace G8scope\Core\Notifications\Channels;

use G8scope\Core\Notifications\NotificationChannel;
use Illuminate\Support\Facades\Http;

class WebhookChannel implements NotificationChannel
{
    public function send(array $payload): void
    {
        $url = $payload['target']['url'] ?? $payload['target'] ?? null;
        if (! is_string($url) || $url === '') {
            return;
        }

        Http::asJson()->post($url, [
            'event' => $payload['event'] ?? 'alert',
            'issue' => $payload['issue'] ?? [],
            'message' => $payload['message'] ?? '',
        ]);
    }
}
