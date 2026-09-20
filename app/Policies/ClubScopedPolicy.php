<?php

namespace App\Policies;

use App\Models\User;

/**
 * Política base de la Fase 9: el administrador puede con todo; el director
 * técnico, sólo con lo que pertenece a su club.
 *
 * Cierra el pendiente 4.1 de `DESPLIEGUE.md`, cuyo disparador anotado era
 * exactamente la llegada del rol técnico: hasta hoy cualquier usuario del
 * panel podía editar cualquier fila, lo cual era inofensivo con un único
 * operador y deja de serlo en cuanto entra alguien que sólo debe tocar su club.
 *
 * Es la SEGUNDA cerradura. La primera es que los recursos del administrador ni
 * siquiera están registrados en el panel `/club` (design D4), así que esto
 * cubre lo que aquella no puede: una URL tecleada a mano dentro del propio
 * panel del técnico.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
abstract class ClubScopedPolicy
{
    /**
     * El club al que pertenece el registro, o null si no se puede determinar
     * —en cuyo caso no es de nadie y sólo el administrador lo toca.
     *
     * @param  TModel  $model
     */
    abstract protected function clubIdOf(mixed $model): ?int;

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * @param  TModel  $model
     */
    public function view(User $user, mixed $model): bool
    {
        return $this->belongsToUsersClub($user, $model);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * @param  TModel  $model
     */
    public function update(User $user, mixed $model): bool
    {
        return $this->belongsToUsersClub($user, $model);
    }

    /**
     * @param  TModel  $model
     */
    public function delete(User $user, mixed $model): bool
    {
        return $this->belongsToUsersClub($user, $model);
    }

    /**
     * @param  TModel  $model
     */
    protected function belongsToUsersClub(User $user, mixed $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $clubId = $this->clubIdOf($model);

        return $clubId !== null && $clubId === $user->club_id;
    }
}
