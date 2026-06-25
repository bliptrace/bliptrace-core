<?php

declare(strict_types=1);

namespace G8Scope\Core\Enums;

use CleaniqueCoders\Traitify\Concerns\InteractsWithEnum;
use CleaniqueCoders\Traitify\Contracts\Enum;

/** Deployment environment of the monitored app. */
enum Environment: string implements Enum
{
    use InteractsWithEnum;

    public function description(): string
    {
        return $this->label();
    }

    case Production = 'production';
    case Staging = 'staging';
    case Local = 'local';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Production => 'red',
            self::Staging => 'amber',
            self::Local => 'zinc',
        };
    }
}
