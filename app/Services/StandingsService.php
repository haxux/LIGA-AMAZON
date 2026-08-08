<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Derives a season's league table live from `Game` rows. Nothing here is
 * persisted or cached — see ARQUITECTURA.md §3 ("se deriva, no se guarda
 * como fuente de verdad") and design D2/D3.
 */
final class StandingsService
{
    /**
     * @return Collection<int, StandingRow> ordered points desc, goal_difference desc, goals_for desc
     */
    public function forSeason(Season $season): Collection
    {
        $rows = Team::query()
            ->where('season_id', $season->getKey())
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Team $team) => [$team->getKey() => [
                'team' => $team,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
            ]])
            ->all();

        $games = Game::query()
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->whereHas('matchday', fn (Builder $query) => $query->where('season_id', $season->getKey()))
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        foreach ($games as $game) {
            $this->accumulate($rows, $game->home_team_id, $game->home_score, $game->away_score);
            $this->accumulate($rows, $game->away_team_id, $game->away_score, $game->home_score);
        }

        return collect($rows)
            ->map(fn (array $row) => new StandingRow(...$row))
            ->sortBy([['points', 'desc'], ['goal_difference', 'desc'], ['goals_for', 'desc']])
            ->values();
    }

    /**
     * @param  array<int, array{team: Team, played: int, won: int, drawn: int, lost: int, goals_for: int, goals_against: int}>  $rows
     */
    private function accumulate(array &$rows, int $teamId, int $for, int $against): void
    {
        if (! isset($rows[$teamId])) {
            return; // team is not on this season's roster — see D5
        }

        $rows[$teamId]['played']++;
        $rows[$teamId]['goals_for'] += $for;
        $rows[$teamId]['goals_against'] += $against;

        if ($for > $against) {
            $rows[$teamId]['won']++;
        } elseif ($for < $against) {
            $rows[$teamId]['lost']++;
        } else {
            $rows[$teamId]['drawn']++;
        }
    }
}
