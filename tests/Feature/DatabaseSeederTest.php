<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_seed_produces_expected_row_counts(): void
    {
        $this->seed();

        $this->assertSame(1, Season::count());
        $this->assertSame(10, Team::count());
        $this->assertSame(10, Stadium::count());
        $this->assertSame(180, Player::count());
        $this->assertSame(18, Matchday::count());
        $this->assertSame(90, Game::count());
    }

    public function test_fresh_seed_leaves_matchdays_eleven_through_eighteen_unscored(): void
    {
        $this->seed();

        $unplayedCount = Game::query()
            ->whereHas('matchday', fn ($query) => $query->where('number', '>=', 11))
            ->whereNull('home_score')
            ->whereNull('away_score')
            ->count();

        $this->assertSame(40, $unplayedCount); // 8 matchdays (11-18) x 5 games
    }

    public function test_fresh_seed_scores_matchdays_one_through_ten(): void
    {
        $this->seed();

        $playedCount = Game::query()
            ->whereHas('matchday', fn ($query) => $query->where('number', '<=', 10))
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->count();

        $this->assertSame(50, $playedCount); // 10 matchdays (1-10) x 5 games
    }

    public function test_fresh_seed_never_pairs_a_team_against_itself(): void
    {
        $this->seed();

        // Guard against a vacuous pass: this only means something once games actually exist.
        $this->assertSame(90, Game::count());

        $equalTeamGames = Game::query()
            ->whereColumn('home_team_id', 'away_team_id')
            ->count();

        $this->assertSame(0, $equalTeamGames);
    }

    public function test_fresh_seed_leaves_all_team_crest_paths_null(): void
    {
        $this->seed();

        // Guard against a vacuous pass: this only means something once teams actually exist.
        $this->assertSame(10, Team::count());

        $this->assertSame(0, Team::whereNotNull('crest_path')->count());
    }

    public function test_fresh_seed_marks_exactly_one_season_as_current(): void
    {
        $this->seed();

        $this->assertSame(1, Season::where('is_current', true)->count());
    }
}
