<?php

namespace App\Services;

use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Season;
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
     * @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit
     */
    public function topScorers(Season $season, ?int $limit = 10): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_GOAL, $limit);
    }

    /**
     * @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit
     */
    public function topAssisters(Season $season, ?int $limit = 10): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_ASSIST, $limit);
    }

    /**
     * @return Collection<int, ScorerRow>
     */
    private function leaderboard(Season $season, string $type, ?int $limit): Collection
    {
        $counts = GameEvent::query()
            ->where('type', $type)
            ->whereHas('game.matchday', fn (Builder $query) => $query->where('season_id', $season->getKey()))
            ->groupBy('player_id')
            ->pluck(DB::raw('COUNT(*)'), 'player_id');

        $rows = Player::query()
            ->whereIn('id', $counts->keys())
            ->with('team')
            ->orderBy('id')
            ->get()
            ->map(fn (Player $player) => new ScorerRow($player, (int) $counts[$player->getKey()]))
            ->sortBy([['count', 'desc']])
            ->values();

        return $limit === null ? $rows : $rows->take($limit);
    }
}
