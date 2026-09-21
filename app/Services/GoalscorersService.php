<?php

namespace App\Services;

use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Season-wide (not division-scoped) goal/assist leaderboards, derived live
 * from `GameEvent` rows — mirrors StandingsService (Fase 4).
 */
final class GoalscorersService
{
    /**
     * @param  ?Team  $team  narrows the board to one club's season squad
     * @param  ?Competition  $competition  liga, una copa o todo (por omisión, todo)
     * @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit
     */
    public function topScorers(Season $season, ?int $limit = 10, ?Team $team = null, ?Competition $competition = null): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_GOAL, $limit, $team, $competition);
    }

    /**
     * @param  ?Team  $team  narrows the board to one club's season squad
     * @param  ?Competition  $competition  liga, una copa o todo (por omisión, todo)
     * @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit
     */
    public function topAssisters(Season $season, ?int $limit = 10, ?Team $team = null, ?Competition $competition = null): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_ASSIST, $limit, $team, $competition);
    }

    /**
     * @param  ?Team  $team  narrows the board to one club's season squad
     * @param  ?Competition  $competition  liga, una copa o todo (por omisión, todo)
     * @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit
     */
    public function topCleanSheets(Season $season, ?int $limit = 10, ?Team $team = null, ?Competition $competition = null): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_CLEAN_SHEET, $limit, $team, $competition);
    }

    /**
     * El filtro por equipo es un parámetro y no un servicio aparte (design
     * D12): la ficha de un club necesita el mismo cómputo, acotado a quienes
     * estuvieron en su plantilla esa temporada. La pertenencia es el puente,
     * porque los eventos cuelgan del jugador y un cedido jugó en dos clubes.
     *
     * La competición es otro parámetro por lo mismo, desde la Fase 15: un
     * goleador de la liga y uno de la copa son dos cifras distintas, y sumarlas
     * sin decirlo es lo que el propietario pidió no hacer.
     *
     * @return Collection<int, ScorerRow>
     */
    private function leaderboard(Season $season, string $type, ?int $limit, ?Team $team = null, ?Competition $competition = null): Collection
    {
        $competition ??= Competition::all();

        $counts = GameEvent::query()
            ->where('type', $type)
            // Por el partido y no por su jornada: uno de copa no tiene jornada,
            // y preguntando por ahí la copa entera se quedaba fuera.
            ->whereHas('game', fn (Builder $query) => $competition->applyTo($query->inSeason($season)))
            ->when($team, fn (Builder $query, Team $team) => $query->whereHas(
                'player.memberships',
                fn (Builder $memberships) => $memberships->where('team_id', $team->getKey()),
            ))
            ->groupBy('player_id')
            ->pluck(DB::raw('COUNT(*)'), 'player_id');

        $rows = Player::query()
            ->whereIn('id', $counts->keys())
            ->with([
                'club',
                // La pertenencia de esta temporada dice dónde jugó realmente,
                // cesiones incluidas; el club propietario queda de respaldo.
                'memberships' => fn ($query) => $query->whereHas(
                    'team',
                    fn (Builder $teamQuery) => $teamQuery->where('season_id', $season->getKey()),
                )->with('team.club'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Player $player) => new ScorerRow(
                $player,
                (int) $counts[$player->getKey()],
                $player->memberships->first()?->team?->club?->short_name ?? $player->club?->short_name,
            ))
            ->sortBy([['count', 'desc']])
            ->values();

        return $limit === null ? $rows : $rows->take($limit);
    }
}
