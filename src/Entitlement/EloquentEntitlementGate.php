<?php

declare(strict_types=1);

namespace G8Scope\Core\Entitlement;

use G8Scope\Core\Contracts\EntitlementGate;
use G8Scope\Core\Enums\ProjectRole;
use G8Scope\Core\Models\Entitlement;
use G8Scope\Core\Models\Membership;
use G8Scope\Core\Models\Project;

/**
 * Account-level entitlement gate (spec §13). One-time, permanent capability flags
 * (custom_api / custom_mcp) + caps. Seats: 1 free owner seat by default.
 */
class EloquentEntitlementGate implements EntitlementGate
{
    public function allows(int $accountId, string $capability): bool
    {
        $entitlement = Entitlement::where('account_id', $accountId)->first();
        if ($entitlement === null || ! $entitlement->unlocked()) {
            return false;
        }

        return match ($capability) {
            'custom_api' => (bool) $entitlement->custom_api,
            'custom_mcp' => (bool) $entitlement->custom_mcp,
            default => false,
        };
    }

    public function seatsRemaining(int $accountId): int
    {
        $entitlement = Entitlement::where('account_id', $accountId)->first();
        $seats = $entitlement?->seats ?? 1;

        $projectIds = Project::where('account_id', $accountId)->pluck('id');

        // The account owner gets a free seat (spec §13). `seats` counts the team
        // members invited *beyond* the owner, so owners never consume a seat.
        $ownerIds = Membership::whereIn('project_id', $projectIds)
            ->where('role', ProjectRole::Owner->value)
            ->distinct()
            ->pluck('user_id');

        $used = Membership::whereIn('project_id', $projectIds)
            ->whereNotIn('user_id', $ownerIds)
            ->distinct()
            ->count('user_id');

        return max(0, $seats - $used);
    }
}
