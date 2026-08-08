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

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_season_and_team_relationship_resolves_both_directions(): void
    {
        $season = Season::factory()->create();
        $team = Team::factory()->for($season)->create();

        $this->assertTrue($season->teams->contains($team));
        $this->assertTrue($team->season->is($season));
    }

    public function test_team_and_player_relationship_resolves_both_directions(): void
    {
        $team = Team::factory()->create();
        $player = Player::factory()->for($team)->create();

        $this->assertTrue($team->players->contains($player));
        $this->assertTrue($player->team->is($team));
    }

    public function test_team_and_stadium_relationship_resolves_both_directions(): void
    {
        $team = Team::factory()->create();
        $stadium = Stadium::factory()->for($team)->create();

        $this->assertTrue($team->stadium->is($stadium));
        $this->assertTrue($stadium->team->is($team));
    }

    public function test_season_and_matchday_relationship_resolves_both_directions(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();

        $this->assertTrue($season->matchdays->contains($matchday));
        $this->assertTrue($matchday->season->is($season));
    }

    public function test_matchday_and_game_relationship_resolves_both_directions(): void
    {
        $season = Season::factory()->create();
        $homeTeam = Team::factory()->for($season)->create();
        $awayTeam = Team::factory()->for($season)->create();
        $matchday = Matchday::factory()->for($season)->create();

        $game = Game::factory()->for($matchday)->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        $this->assertTrue($matchday->games->contains($game));
        $this->assertTrue($game->matchday->is($matchday));
    }

    public function test_game_home_and_away_team_relationships_resolve_both_directions(): void
    {
        $season = Season::factory()->create();
        $homeTeam = Team::factory()->for($season)->create();
        $awayTeam = Team::factory()->for($season)->create();
        $matchday = Matchday::factory()->for($season)->create();

        $game = Game::factory()->for($matchday)->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        $this->assertTrue($game->homeTeam->is($homeTeam));
        $this->assertTrue($game->awayTeam->is($awayTeam));
        $this->assertTrue($homeTeam->homeGames->contains($game));
        $this->assertTrue($awayTeam->awayGames->contains($game));
    }
}
