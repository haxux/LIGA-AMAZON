<?php

namespace App\Policies;

use App\Models\Team;

/**
 * @extends ClubScopedPolicy<Team>
 */
class TeamPolicy extends ClubScopedPolicy
{
    protected function clubIdOf(mixed $model): ?int
    {
        return $model->club_id;
    }
}
