<?php

declare(strict_types=1);

namespace G8Scope\Core\Enums;

/** Endpoint isolation type (spec §12): shared multi-tenant vs dedicated. */
enum EndpointType: string
{
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
