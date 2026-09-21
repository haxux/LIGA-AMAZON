<?php

namespace App\Http\Controllers\Site;

use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Services\ClubSeasonService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * La ficha de un club (Fase 11), con sus seis pestañas.
 *
 * La ficha es del CLUB y la temporada es un filtro dentro de ella: por eso un
 * club que este año no está inscrito sigue teniendo página, con su palmarés y
 * su historia. Es exactamente lo que la identidad permanente de la Fase 9
 * existe para permitir.
 */
class ClubProfileController extends SiteController
{
    /**
     * Las seis pestañas, en orden, con su etiqueta. La primera es la que se
     * abre por omisión.
     *
     * @var array<string, string>
     */
    public const TABS = [
        'general' => 'General',
        'partidos' => 'Partidos',
        'jugadores' => 'Jugadores',
        'trofeos' => 'Trofeos',
        'stats' => 'Stats',
        'tecnico' => 'Técnico',
    ];

    public function __invoke(Request $request, ClubSeasonService $clubSeason, Club $club, ?string $tab = null): View
    {
        // Una pestaña inventada cae en General, igual que una ?jornada que no
        // existe cae en la jornada en curso: la URL de un sitio público la
        // escribe cualquiera, y un 404 por una palabra mal escrita no ayuda.
        $tab = array_key_exists((string) $tab, self::TABS) ? (string) $tab : array_key_first(self::TABS);

        $seasons = $this->seasonsOf($club);
        $season = $seasons->firstWhere('id', $request->integer('temporada'))
            ?? $seasons->firstWhere('id', $this->seasons->active()?->getKey())
            ?? $seasons->first();

        $team = $season === null ? null : Team::query()
            ->where('club_id', $club->getKey())
            ->where('season_id', $season->getKey())
            ->with(['division', 'season', 'lineup.slots.player'])
            ->first();

        return view('site.clubs.show', [
            'club' => $club->load('stadium'),
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'team' => $team,
            'tab' => $tab,
            'tabs' => self::TABS,
        ] + $this->dataFor($tab, $club, $team, $clubSeason));
    }

    /**
     * Sólo las temporadas que el club jugó: ofrecer las demás daría una ficha
     * vacía por elegir bien.
     *
     * @return Collection<int, Season>
     */
    private function seasonsOf(Club $club): Collection
    {
        return Season::query()
            ->whereHas('teams', fn ($query) => $query->where('club_id', $club->getKey()))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Cada pestaña pide lo suyo y nada más: abrir Trofeos no debería costar la
     * clasificación entera de la temporada.
     *
     * @return array<string, mixed>
     */
    private function dataFor(string $tab, Club $club, ?Team $team, ClubSeasonService $clubSeason): array
    {
        if ($tab === 'trofeos') {
            // El palmarés ENTERO, no el de la temporada elegida: un título se
            // gana una vez y se exhibe siempre.
            return ['trophies' => $club->trophies()->with('season')->get()
                ->sortByDesc(fn ($trophy) => [$trophy->season?->start_date, $trophy->name])
                ->values()];
        }

        if ($tab === 'tecnico') {
            return ['coach' => $club->coach];
        }

        if ($team === null) {
            return [];
        }

        return match ($tab) {
            'general' => [
                'nextGame' => $clubSeason->nextGame($team),
                'recentResults' => $clubSeason->recentResults($team),
                'position' => $clubSeason->position($team),
                'topScorer' => $clubSeason->topScorer($team),
                'topAssister' => $clubSeason->topAssister($team),
                'lineup' => $team->lineup,
            ],
            'partidos' => ['games' => $clubSeason->games($team)],
            'jugadores' => ['squad' => $this->squad($team)],
            'stats' => ['stats' => $clubSeason->stats($team)],
            default => [],
        };
    }

    /**
     * La plantilla de esa temporada agrupada por posición general, que es el
     * orden en el que se lee una plantilla — y el motivo por el que la posición
     * específica tiene que pertenecer a la general (Fase 10).
     *
     * @return array<int, array{position: string, members: Collection<int, SquadMembership>}>
     */
    private function squad(Team $team): array
    {
        $members = $team->memberships()->with('player')->get()
            ->sortBy(fn (SquadMembership $membership) => $membership->shirt_number ?? PHP_INT_MAX);

        return collect(Player::POSITIONS)
            ->map(fn (string $position) => [
                'position' => $position,
                'members' => $members
                    ->filter(fn (SquadMembership $membership) => $membership->player?->position === $position)
                    ->values(),
            ])
            ->filter(fn (array $group) => $group['members']->isNotEmpty())
            ->values()
            ->all();
    }
}
