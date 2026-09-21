<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Un hilo es de sus dos participantes y de nadie más — tampoco del
 * administrador, que en el chat es un presidente como otro cualquiera y sólo ve
 * sus propias conversaciones. Es la única cosa de esta aplicación que el
 * administrador no puede leer entera, y es a propósito: un chat que el
 * administrador lee por encima del hombro no es un chat.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->includes($user);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return false;
    }
}
