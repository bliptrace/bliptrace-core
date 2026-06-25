<?php

declare(strict_types=1);

namespace G8Scope\Core\Enums;

use CleaniqueCoders\Traitify\Concerns\InteractsWithEnum;
use CleaniqueCoders\Traitify\Contracts\Enum;

/** RBAC roles (spec §14). */
enum ProjectRole: string implements Enum
{
    use InteractsWithEnum;

    public function description(): string
    {
        return $this->label();
    }

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

    /**
     * Abilities granted by this role. `Owner` is a superset (`*`).
     *
     * Vocabulary: view (read), triage (assign / status / incidents),
     * manage_keys, manage_members, manage_rules, update (rename), delete.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => ['*'],
            self::Admin => ['view', 'triage', 'manage_keys', 'manage_members', 'manage_rules', 'update'],
            self::Member => ['view', 'triage'],
            self::Viewer => ['view'],
        };
    }

    /** Whether this role grants the given ability. */
    public function can(string $ability): bool
    {
        $permissions = $this->permissions();

        return in_array('*', $permissions, true) || in_array($ability, $permissions, true);
    }
}
