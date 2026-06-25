<?php

declare(strict_types=1);

namespace G8scope\Core\Enums;

/** Deployment environment of the monitored app. */
enum Environment: string
{
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
