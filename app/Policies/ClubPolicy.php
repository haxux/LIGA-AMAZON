<?php

namespace App\Policies;

use App\Models\Club;

/**
 * @extends ClubScopedPolicy<Club>
 */
class ClubPolicy extends ClubScopedPolicy
{
    protected function clubIdOf(mixed $model): ?int
    {
        return $model->getKey();
    }
}
