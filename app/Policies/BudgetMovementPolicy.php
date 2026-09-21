<?php

namespace App\Policies;

use App\Models\BudgetMovement;
use App\Models\User;

/**
 * @extends ClubScopedPolicy<BudgetMovement>
 */
class BudgetMovementPolicy extends ClubScopedPolicy
{
    protected function clubIdOf(mixed $model): ?int
    {
        return $model->club_id;
    }

    /**
     * Un movimiento del libro no se edita ni se borra desde el lado del
     * técnico, aunque sea de su club: lo que propuso ya está dicho, y
     * responderlo es del administrador. Es la diferencia con el resto de
     * recursos de su panel, donde sí escribe.
     */
    public function update(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }
}
