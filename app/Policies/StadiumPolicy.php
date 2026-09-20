<?php

namespace App\Policies;

use App\Models\Stadium;

/**
 * @extends ClubScopedPolicy<Stadium>
 */
class StadiumPolicy extends ClubScopedPolicy
{
    protected function clubIdOf(mixed $model): ?int
    {
        return $model->club_id;
    }
}
