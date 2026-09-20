<?php

namespace App\Policies;

use App\Models\SquadMembership;

/**
 * La pertenencia es del club que inscribe al equipo, no del club propietario
 * del jugador: en una cesión, quien la gestiona es el club donde juega.
 *
 * @extends ClubScopedPolicy<SquadMembership>
 */
class SquadMembershipPolicy extends ClubScopedPolicy
{
    protected function clubIdOf(mixed $model): ?int
    {
        return $model->team?->club_id;
    }
}
