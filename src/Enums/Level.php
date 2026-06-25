<?php

declare(strict_types=1);

namespace G8Scope\Core\Enums;

/** Envelope severity (spec §10). */
enum Level: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Fatal = 'fatal';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Debug => 'zinc',
            self::Info => 'blue',
            self::Warning => 'amber',
            self::Error => 'red',
            self::Fatal => 'rose',
        };
    }
}
