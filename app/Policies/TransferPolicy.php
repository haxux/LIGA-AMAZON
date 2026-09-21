<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

/**
 * Un traspaso pertenece a DOS clubes, así que la política base por un único
 * club no sirve: el técnico ve los suyos —los que entran y los que salen— y no
 * escribe ninguno, porque registrarlos es del administrador.
 */
class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transfer $transfer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return in_array($user->club_id, [$transfer->from_club_id, $transfer->to_club_id], true);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return $user->isAdmin();
    }
}
