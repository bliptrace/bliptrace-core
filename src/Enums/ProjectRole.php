<?php

declare(strict_types=1);

namespace G8scope\Core\Enums;

/** RBAC roles (spec §14). */
enum ProjectRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Owner => 'purple',
            self::Admin => 'blue',
            self::Member => 'green',
            self::Viewer => 'zinc',
        };
    }
}
