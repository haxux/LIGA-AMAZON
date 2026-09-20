<?php

namespace App\Policies;

use App\Models\Player;

/**
 * @extends ClubScopedPolicy<Player>
 */
class PlayerPolicy extends ClubScopedPolicy
{
    protected function clubIdOf(mixed $model): ?int
    {
        return $model->club_id;
    }
}
