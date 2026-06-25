<?php

declare(strict_types=1);

namespace G8Scope\Core\Enums;

use CleaniqueCoders\Traitify\Concerns\InteractsWithEnum;
use CleaniqueCoders\Traitify\Contracts\Enum;

/** Report output formats (spec §16, ReportGenerator driver). */
enum ReportType: string implements Enum
{
    use InteractsWithEnum;

    public function description(): string
    {
        return $this->label();
    }

    case Pdf = 'pdf';
    case Csv = 'csv';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pdf => 'red',
            self::Csv => 'green',
        };
    }
}
