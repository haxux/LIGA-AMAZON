<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Services\GoalscorersService;
use App\Services\ScorerRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalscorersServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(Season $season): Game
    {
        $matchday = Matchday::factory()->for($season)->create();
        $home = Team::factory()->for($season)->create();
        $away = Team::factory()->for($season)->create();

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);
    }

    public function test_top_scorers_counts_only_goal_events(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $player = Player::factory()->create(['team_id' => $game->home_team_id]);

        GameEvent::factory()->for($game)->for($player)->goal()->create();
        GameEvent::factory()->for($game)->for($player)->goal()->create();
        GameEvent::factory()->for($game)->for($player)->assist()->create();

        $rows = (new GoalscorersService)->topScorers($season);
        $row = $rows->firstWhere(fn (ScorerRow $r) => $r->player->is($player));

        $this->assertNotNull($row);
        $this->assertSame(2, $row->count);
    }

    public function test_top_assisters_counts_only_assist_events(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $player = Player::factory()->create(['team_id' => $game->home_team_id]);

        GameEvent::factory()->for($game)->for($player)->assist()->create();
        GameEvent::factory()->for($game)->for($player)->assist()->create();
        GameEvent::factory()->for($game)->for($player)->assist()->create();
        GameEvent::factory()->for($game)->for($player)->goal()->create();

        $rows = (new GoalscorersService)->topAssisters($season);
        $row = $rows->firstWhere(fn (ScorerRow $r) => $r->player->is($player));

        $this->assertNotNull($row);
        $this->assertSame(3, $row->count);
    }

    public function test_events_from_another_season_are_excluded(): void
    {
        $season = Season::factory()->create();
        $otherSeason = Season::factory()->create();
        $game = $this->makeGame($season);
        $otherGame = $this->makeGame($otherSeason);
        $player = Player::factory()->create(['team_id' => $game->home_team_id]);
        $otherPlayer = Player::factory()->create(['team_id' => $otherGame->home_team_id]);

        GameEvent::factory()->for($game)->for($player)->goal()->create();
        GameEvent::factory()->for($otherGame)->for($otherPlayer)->goal()->create();

        $rows = (new GoalscorersService)->topScorers($season);
        $playerIds = $rows->map(fn (ScorerRow $r) => $r->player->id)->all();

        $this->assertContains($player->id, $playerIds);
        $this->assertNotContains($otherPlayer->id, $playerIds);
    }

    public function test_players_with_no_events_are_absent(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $playerWithGoal = Player::factory()->create(['team_id' => $game->home_team_id]);
        $playerWithoutEvents = Player::factory()->create(['team_id' => $game->away_team_id]);

        GameEvent::factory()->for($game)->for($playerWithGoal)->goal()->create();

        $rows = (new GoalscorersService)->topScorers($season);
        $playerIds = $rows->map(fn (ScorerRow $r) => $r->player->id)->all();

        $this->assertContains($playerWithGoal->id, $playerIds);
        $this->assertNotContains($playerWithoutEvents->id, $playerIds);
    }

    public function test_rows_are_ordered_by_count_descending(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);
        $topScorer = Player::factory()->create(['team_id' => $game->home_team_id, 'name' => 'Top Scorer']);
        $lowScorer = Player::factory()->create(['team_id' => $game->away_team_id, 'name' => 'Low Scorer']);

        GameEvent::factory()->for($game)->for($topScorer)->goal()->count(3)->create();
        GameEvent::factory()->for($game)->for($lowScorer)->goal()->count(1)->create();

        $rows = (new GoalscorersService)->topScorers($season)->values();

        $this->assertSame('Top Scorer', $rows[0]->player->name);
        $this->assertSame('Low Scorer', $rows[1]->player->name);
    }

    public function test_result_is_limited_to_ten_by_default(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);

        collect(range(1, 12))->each(function (int $i) use ($game) {
            $player = Player::factory()->create(['team_id' => $game->home_team_id, 'shirt_number' => $i]);
            GameEvent::factory()->for($game)->for($player)->goal()->create();
        });

        $rows = (new GoalscorersService)->topScorers($season);

        $this->assertSame(10, $rows->count());
    }

    public function test_null_limit_returns_every_scorer(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);

        collect(range(1, 12))->each(function (int $i) use ($game) {
            $player = Player::factory()->create(['team_id' => $game->home_team_id, 'shirt_number' => $i]);
            GameEvent::factory()->for($game)->for($player)->goal()->create();
        });

        $rows = (new GoalscorersService)->topScorers($season, null);

        $this->assertSame(12, $rows->count());
    }

    public function test_tied_counts_keep_a_stable_order(): void
    {
        $season = Season::factory()->create();
        $game = $this->makeGame($season);

        $playerLowerId = Player::factory()->create(['team_id' => $game->home_team_id]);
        $playerHigherId = Player::factory()->create(['team_id' => $game->away_team_id]);

        GameEvent::factory()->for($game)->for($playerHigherId)->goal()->create();
        GameEvent::factory()->for($game)->for($playerLowerId)->goal()->create();

        $rows = (new GoalscorersService)->topScorers($season)->values();
        $order = $rows->map(fn (ScorerRow $r) => $r->player->id)->all();

        $this->assertSame([$playerLowerId->id, $playerHigherId->id], $order);

        $again = (new GoalscorersService)->topScorers($season)->values()
            ->map(fn (ScorerRow $r) => $r->player->id)->all();

        $this->assertSame($order, $again);
    }
}
