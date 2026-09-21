<?php

namespace App\Http\Controllers\Site;

use App\Models\Club;
use App\Models\Game;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Services\ClubSeasonService;
use App\Services\ClubSeasonStats;
use App\Services\Competition;
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
        ] + $this->dataFor($tab, $club, $team, $clubSeason, $request));
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
    private function dataFor(string $tab, Club $club, ?Team $team, ClubSeasonService $clubSeason, Request $request): array
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
                'canEditLineup' => $this->coachMayEditLineup($team),
            ],
            'partidos' => $this->fixturesTab($team, $clubSeason, $request),
            'jugadores' => ['squad' => $this->squad($team)],
            'stats' => $this->statsTab($team, $clubSeason),
            default => [],
        };
    }

    /**
     * El calendario, filtrable por competición y **empezando por la liga**
     * (decisión del propietario): es la competición que un club mira a diario,
     * y con la copa mezclada la jornada que viene queda enterrada entre cruces.
     *
     * Aquí se filtra y no se separa, al revés que en las cifras: un calendario
     * es largo, y verlos todos seguidos es justamente lo que se quería evitar.
     *
     * Un club que no juega ninguna copa no tiene nada que elegir, y su pestaña
     * queda como estaba.
     *
     * @return array<string, mixed>
     */
    private function fixturesTab(Team $team, ClubSeasonService $clubSeason, Request $request): array
    {
        $competitions = Competition::forTeam($team);
        $competition = Competition::resolve(
            $competitions,
            $request->string('competicion')->toString(),
            Competition::league(),
        );

        return [
            'games' => $clubSeason->games($team)->filter(
                fn (Game $game) => $competition->matches($game),
            )->values(),
            'competition' => $competition,
            'competitionOptions' => $competitions->count() > 2 ? Competition::asSelectOptions($competitions) : [],
        ];
    }

    /**
     * Las cifras del club, separadas por competición desde la Fase 15: primero
     * las de la temporada entera —«General»— y después una tanda por cada
     * competición que el club DISPUTA, la liga y cada copa.
     *
     * Separadas y no filtradas (decisión del propietario): lo que se quiere ver
     * de un vistazo es cuánto de lo hecho es de liga y cuánto de copa, y con un
     * selector eso obliga a ir y volver recordando cifras.
     *
     * Un club que no juega ninguna copa no tiene nada que separar —«General» y
     * «Liga» serían la misma tanda dos veces—, y su pestaña queda como estaba.
     *
     * @return array<string, mixed>
     */
    private function statsTab(Team $team, ClubSeasonService $clubSeason): array
    {
        $competitions = Competition::forTeam($team);

        // Sin copas manda la liga, que es la única con puntos: un club que no
        // juega copa no debería perder su casilla de puntos.
        if ($competitions->count() <= 2) {
            return ['statBlocks' => collect([$this->statBlock($team, $clubSeason, Competition::league(), null)])];
        }

        return ['statBlocks' => $competitions->map(fn (Competition $competition) => $this->statBlock(
            $team,
            $clubSeason,
            $competition,
            $competition->isAll() ? 'General' : $competition->label,
        ))];
    }

    /**
     * @return array{competition: Competition, title: ?string, stats: ClubSeasonStats}
     */
    private function statBlock(Team $team, ClubSeasonService $clubSeason, Competition $competition, ?string $title): array
    {
        return [
            'competition' => $competition,
            'title' => $title,
            'stats' => $clubSeason->stats($team, $competition),
        ];
    }

    /**
     * El once se arma desde aquí, y sólo lo arma el técnico de ESTE club en la
     * temporada vigente: la de un año cerrado es historia, no un borrador.
     *
     * El técnico se reconoce por el guard `club`, el del panel, que vive en la
     * misma sesión que el sitio público. Para todos los demás —visitante
     * anónimo, otro técnico, el propio técnico mirando otra temporada— esta
     * página sigue siendo exactamente la de siempre.
     */
    private function coachMayEditLineup(Team $team): bool
    {
        $user = auth('club')->user();

        return $user !== null
            && $user->isCoach()
            && (int) $user->club_id === (int) $team->club_id
            && (int) $team->season_id === (int) $this->seasons->active()?->getKey();
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
