<?php

declare(strict_types=1);

namespace G8Scope\Core\Notifications\Channels;

use G8Scope\Core\Notifications\NotificationChannel;
use Illuminate\Support\Facades\Mail;

class MailChannel implements NotificationChannel
{
    public function send(array $payload): void
    {
        $to = $payload['target']['email'] ?? $payload['target'] ?? null;
        if (! is_string($to) || $to === '') {
            return;
        }

        $message = (string) ($payload['message'] ?? 'g8scope alert');
        Mail::raw($message, static function ($mail) use ($to): void {
            $mail->to($to)->subject('[g8scope] alert');
        });
    }
}
