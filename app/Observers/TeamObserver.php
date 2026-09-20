<?php

namespace App\Observers;

use App\Models\SquadMembership;
use App\Models\Team;

class TeamObserver
{
    /**
     * Inscribir un club en una temporada arrastra la plantilla que tenía en su
     * participación anterior, con sus dorsales: sin esto, cada temporada nueva
     * obligaría a teclear la plantilla entera, que es justo lo que la Fase 9
     * viene a quitar de en medio (design D1c).
     *
     * Sólo se heredan las pertenencias en propiedad. Una cesión termina con su
     * temporada — heredarla daría por hecho que el club prestatario la renueva,
     * y eso lo decide alguien, no una migración de datos.
     *
     * Queda mudo bajo `WithoutModelEvents`, que es lo que usa `DatabaseSeeder`:
     * el seeder arma sus plantillas explícitamente, como los guards de `Game`,
     * `Season` y `Matchday`.
     */
    public function created(Team $team): void
    {
        $previous = Team::query()
            ->select('teams.*')
            ->leftJoin('seasons', 'seasons.id', '=', 'teams.season_id')
            ->where('teams.club_id', $team->club_id)
            ->whereKeyNot($team->getKey())
            ->whereHas('memberships')
            ->orderByDesc('seasons.start_date')
            ->orderByDesc('teams.id')
            ->first();

        if ($previous === null) {
            return;
        }

        $previous->memberships()
            ->where('type', SquadMembership::TYPE_OWNED)
            ->get()
            ->each(fn (SquadMembership $membership) => SquadMembership::create([
                'team_id' => $team->getKey(),
                'player_id' => $membership->player_id,
                'shirt_number' => $membership->shirt_number,
                'type' => SquadMembership::TYPE_OWNED,
            ]));
    }
}
