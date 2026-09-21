<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * La temporada de un club vista desde su ficha pública (Fase 11): próximo
 * partido, forma reciente, puesto, estadísticas y quién marcó y asistió más.
 *
 * Todo se deriva de `games` y `game_events` en el momento (design D12), y el
 * puesto y el goleador se piden a los servicios que ya los saben calcular
 * —`StandingsService` y `GoalscorersService`— en lugar de rehacerlos aquí.
 */
final class ClubSeasonService
{
    public function __construct(
        private readonly StandingsService $standings,
        private readonly GoalscorersService $goalscorers,
    ) {}

    /**
     * Todos los partidos del equipo en su temporada, de la primera jornada a la
     * última. Un equipo pertenece a UNA temporada, así que no hace falta
     * acotarla: sus partidos son los de ese año por construcción.
     *
     * @return Collection<int, Game>
     */
    public function games(Team $team): Collection
    {
        return Game::query()
            ->where(fn (Builder $query) => $query
                ->where('home_team_id', $team->getKey())
                ->orWhere('away_team_id', $team->getKey()))
            ->with(['matchday', 'homeTeam.club', 'awayTeam.club'])
            ->get()
            // Una sola clave compuesta, y no dos criterios: el multiorden de
            // Collection se desordena cuando el segundo criterio mezcla fechas
            // con nulos, y aquí la fecha es opcional. Con una tupla, PHP
            // compara elemento a elemento y el resultado es el esperado.
            ->sortBy(fn (Game $game) => [
                $game->matchday?->number ?? PHP_INT_MAX,
                $game->kickoff_at?->getTimestamp() ?? PHP_INT_MAX,
                $game->getKey(),
            ])
            ->values();
    }

    /**
     * El siguiente partido por jugar. "Por jugar" es no tener marcador, no
     * tener fecha futura: la fecha es opcional en este modelo y muchos partidos
     * se cargan sin ella.
     */
    public function nextGame(Team $team): ?Game
    {
        return $this->games($team)->first(fn (Game $game) => ! $this->isPlayed($game));
    }

    /**
     * Los últimos resultados, el más reciente primero, con la letra que le toca
     * al equipo de esta ficha: G, E o P. La vista no debería tener que mirar si
     * jugaba en casa para saber si ganó.
     *
     * @return Collection<int, array{game: Game, outcome: string, opponent: ?Team, scored: int, conceded: int}>
     */
    public function recentResults(Team $team, int $limit = 5): Collection
    {
        return $this->games($team)
            ->filter(fn (Game $game) => $this->isPlayed($game))
            ->reverse()
            ->take($limit)
            ->map(function (Game $game) use ($team): array {
                $atHome = (int) $game->home_team_id === (int) $team->getKey();
                $scored = $atHome ? $game->home_score : $game->away_score;
                $conceded = $atHome ? $game->away_score : $game->home_score;

                return [
                    'game' => $game,
                    'outcome' => $scored > $conceded ? 'G' : ($scored < $conceded ? 'P' : 'E'),
                    'opponent' => $atHome ? $game->awayTeam : $game->homeTeam,
                    'scored' => (int) $scored,
                    'conceded' => (int) $conceded,
                ];
            })
            ->values();
    }

    /**
     * El puesto en la tabla de su división, o en la de la temporada entera
     * cuando el equipo no tiene división asignada — que es posible, porque
     * `teams.division_id` es nullable.
     */
    public function position(Team $team): ?int
    {
        $table = $team->division_id === null || $team->division === null
            ? $this->standings->forSeason($team->season)
            : $this->standings->forDivision($team->division);

        $index = $table->search(fn (StandingRow $row) => (int) $row->team->getKey() === (int) $team->getKey());

        return $index === false ? null : $index + 1;
    }

    public function stats(Team $team): ClubSeasonStats
    {
        $played = $this->games($team)->filter(fn (Game $game) => $this->isPlayed($game));

        $counters = ['won' => 0, 'drawn' => 0, 'lost' => 0, 'goals_for' => 0, 'goals_against' => 0];

        foreach ($played as $game) {
            $atHome = (int) $game->home_team_id === (int) $team->getKey();
            $for = (int) ($atHome ? $game->home_score : $game->away_score);
            $against = (int) ($atHome ? $game->away_score : $game->home_score);

            $counters['goals_for'] += $for;
            $counters['goals_against'] += $against;
            $counters[$for > $against ? 'won' : ($for < $against ? 'lost' : 'drawn')]++;
        }

        $events = $this->eventCounts($team);

        return new ClubSeasonStats(
            ...$counters + [
                'played' => $played->count(),
                'yellow_cards' => $events[GameEvent::TYPE_YELLOW_CARD] ?? 0,
                'red_cards' => $events[GameEvent::TYPE_RED_CARD] ?? 0,
                'clean_sheets' => $events[GameEvent::TYPE_CLEAN_SHEET] ?? 0,
            ],
        );
    }

    public function topScorer(Team $team): ?ScorerRow
    {
        return $this->goalscorers->topScorers($team->season, 1, $team)->first();
    }

    public function topAssister(Team $team): ?ScorerRow
    {
        return $this->goalscorers->topAssisters($team->season, 1, $team)->first();
    }

    /**
     * Tarjetas y porterías a cero de quienes formaron parte de ESTA plantilla.
     * Los eventos cuelgan del jugador, así que el puente es la pertenencia de la
     * temporada: sin ella, un jugador cedido sumaría sus tarjetas a los dos
     * clubes.
     *
     * @return array<string, int>
     */
    private function eventCounts(Team $team): array
    {
        return GameEvent::query()
            ->whereHas('game.matchday', fn (Builder $query) => $query->where('season_id', $team->season_id))
            ->whereHas('player.memberships', fn (Builder $query) => $query->where('team_id', $team->getKey()))
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function isPlayed(Game $game): bool
    {
        return $game->home_score !== null && $game->away_score !== null;
    }
}
