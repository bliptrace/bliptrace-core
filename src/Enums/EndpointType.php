<?php

declare(strict_types=1);

namespace G8Scope\Core\Enums;

use CleaniqueCoders\Traitify\Concerns\InteractsWithEnum;
use CleaniqueCoders\Traitify\Contracts\Enum;

/** Endpoint isolation type (spec §12): shared multi-tenant vs dedicated. */
enum EndpointType: string implements Enum
{
    use InteractsWithEnum;

    public function description(): string
    {
        return $this->label();
    }

    case Shared = 'shared';
    case Dedicated = 'dedicated';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Shared => 'zinc',
            self::Dedicated => 'purple',
        };
    }
}
