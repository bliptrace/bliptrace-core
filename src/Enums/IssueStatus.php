<?php

declare(strict_types=1);

namespace G8Scope\Core\Enums;

/** Issue lifecycle (spec §5): open / resolve / reopen / mute / ack. */
enum IssueStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Muted = 'muted';
    case Acknowledged = 'acknowledged';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Muted => 'Muted',
            self::Acknowledged => 'Acknowledged',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'red',
            self::Resolved => 'green',
            self::Muted => 'zinc',
            self::Acknowledged => 'amber',
        };
    }
}
