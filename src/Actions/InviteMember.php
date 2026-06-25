<?php

declare(strict_types=1);

namespace G8scope\Core\Actions;

use G8scope\Core\Contracts\EntitlementGate;
use G8scope\Core\Enums\ProjectRole;
use G8scope\Core\Exceptions\SeatLimitException;
use G8scope\Core\Models\Membership;
use G8scope\Core\Models\Project;

/**
 * Invite a user to a project (spec §6, §18). A brand-new user (not yet a member of
 * any project in the account) consumes a seat; reject when none remain.
 */
class InviteMember
{
    public function __construct(private readonly EntitlementGate $gate) {}

    public function __invoke(Project $project, int $userId, ProjectRole $role = ProjectRole::Member): Membership
    {
        $accountProjectIds = Project::where('account_id', $project->account_id)->pluck('id');
        $isNewToAccount = ! Membership::whereIn('project_id', $accountProjectIds)
            ->where('user_id', $userId)
            ->exists();

        if ($isNewToAccount && $this->gate->seatsRemaining($project->account_id) <= 0) {
            throw new SeatLimitException('No seats remaining; add a seat to invite more members.');
        }

        return Membership::firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $userId],
            ['role' => $role->value],
        );
    }
}
