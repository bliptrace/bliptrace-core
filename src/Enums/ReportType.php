<?php

declare(strict_types=1);

namespace G8scope\Core\Enums;

/** Report output formats (spec §16, ReportGenerator driver). */
enum ReportType: string
{
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
