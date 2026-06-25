<?php

declare(strict_types=1);

namespace G8Scope\Core\Enums;

use CleaniqueCoders\Traitify\Concerns\InteractsWithEnum;
use CleaniqueCoders\Traitify\Contracts\Enum;

/** g8desk support link status (spec §15). Mirrors g8desk's flow. */
enum SupportStatus: string implements Enum
{
    use InteractsWithEnum;

    public function description(): string
    {
        return $this->label();
    }

    case Open = 'open';
    case InProgress = 'in_progress';
    case PendingClient = 'pending_client';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::PendingClient => 'Pending Client',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::InProgress => 'amber',
            self::PendingClient => 'purple',
            self::Resolved => 'green',
            self::Closed => 'zinc',
        };
    }
}
