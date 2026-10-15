<?php

namespace App\Policies;

use App\Models\Suggestion;
use App\Models\User;

/**
 * Cualquier técnico manda sugerencias y lee las suyas; el administrador las lee
 * todas y decide qué se hace con ellas. Nadie edita el texto de otro, ni el
 * suyo una vez enviado: lo mandado es lo que se leyó.
 */
class SuggestionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Suggestion $suggestion): bool
    {
        return $user->isAdmin() || (int) $suggestion->user_id === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->isCoach();
    }

    public function update(User $user, Suggestion $suggestion): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Suggestion $suggestion): bool
    {
        return $user->isAdmin();
    }
}
