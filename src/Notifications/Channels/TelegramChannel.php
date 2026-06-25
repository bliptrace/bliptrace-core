<?php

declare(strict_types=1);

namespace G8Scope\Core\Notifications\Channels;

use G8Scope\Core\Notifications\NotificationChannel;
use Illuminate\Support\Facades\Http;

class TelegramChannel implements NotificationChannel
{
    public function send(array $payload): void
    {
        $token = $payload['target']['token'] ?? null;
        $chatId = $payload['target']['chat_id'] ?? null;
        if (! is_string($token) || ! $chatId) {
            return;
        }

        Http::asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $payload['message'] ?? 'g8scope alert',
        ]);
    }
}
