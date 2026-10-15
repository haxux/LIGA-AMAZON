<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

/**
 * Un traspaso pertenece a DOS clubes, así que la política base por un único
 * club no sirve: el técnico ve los suyos —los que entran y los que salen— y no
 * escribe ninguno desde su panel.
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

    /**
     * Sólo el administrador registra traspasos. Los del técnico nacen del chat
     * (al aceptarse una oferta) y no pasan por la política: es el servicio
     * quien los escribe, como propuesta.
     */
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
