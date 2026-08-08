<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixturesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_played_game_shows_its_score(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $matchday = Matchday::factory()->for($season)->create(['number' => 1]);
        $home = Team::factory()->for($season)->create(['name' => 'Manaos FC']);
        $away = Team::factory()->for($season)->create(['name' => 'Tapajós SC']);
        Game::factory()->for($matchday)->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => 3,
            'away_score' => 1,
        ]);

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertSee('Tapajós SC')
            ->assertSee('3')
            ->assertSee('1');
    }

    public function test_unplayed_game_shows_no_score(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $matchday = Matchday::factory()->for($season)->create(['number' => 1]);
        $home = Team::factory()->for($season)->create(['name' => 'Amazonas Royals']);
        $away = Team::factory()->for($season)->create(['name' => 'Belém Athletic']);
        Game::factory()->for($matchday)->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => null,
            'away_score' => null,
        ]);

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('Amazonas Royals')
            ->assertSee('Belém Athletic');
    }

    public function test_page_groups_games_by_matchday(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $matchday1 = Matchday::factory()->for($season)->create(['number' => 1]);
        $matchday2 = Matchday::factory()->for($season)->create(['number' => 2]);
        $home = Team::factory()->for($season)->create();
        $away = Team::factory()->for($season)->create();
        Game::factory()->for($matchday1)->create(['home_team_id' => $home->id, 'away_team_id' => $away->id]);
        Game::factory()->for($matchday2)->create(['home_team_id' => $home->id, 'away_team_id' => $away->id]);

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSeeInOrder(['1', '2']);
    }
}
