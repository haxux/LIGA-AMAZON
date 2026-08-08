<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScorersPageTest extends TestCase
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

    public function test_page_lists_top_scorers_and_assisters(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $game = $this->makeGame($season);
        $scorer = Player::factory()->create(['team_id' => $game->home_team_id, 'name' => 'Top Scorer']);
        $assister = Player::factory()->create(['team_id' => $game->away_team_id, 'name' => 'Top Assister']);

        GameEvent::factory()->for($game)->for($scorer)->goal()->create();
        GameEvent::factory()->for($game)->for($assister)->assist()->create();

        $this->get(route('site.scorers'))
            ->assertOk()
            ->assertSee('Top Scorer')
            ->assertSee('Top Assister');
    }

    public function test_page_omits_players_with_no_events(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $game = $this->makeGame($season);
        $scorer = Player::factory()->create(['team_id' => $game->home_team_id, 'name' => 'Has Goal']);
        Player::factory()->create(['team_id' => $game->away_team_id, 'name' => 'No Events Player']);

        GameEvent::factory()->for($game)->for($scorer)->goal()->create();

        $this->get(route('site.scorers'))
            ->assertOk()
            ->assertSee('Has Goal')
            ->assertDontSee('No Events Player');
    }

    public function test_page_renders_a_short_list_without_placeholder_rows(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $game = $this->makeGame($season);

        collect(range(1, 3))->each(function (int $i) use ($game) {
            $player = Player::factory()->create(['team_id' => $game->home_team_id, 'shirt_number' => $i, 'name' => "Scorer {$i}"]);
            GameEvent::factory()->for($game)->for($player)->goal()->create();
        });

        $response = $this->get(route('site.scorers'))->assertOk();
        $response->assertSee('Scorer 1');
        $response->assertSee('Scorer 2');
        $response->assertSee('Scorer 3');
    }

    public function test_page_shows_at_most_ten_of_each(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $game = $this->makeGame($season);

        // All tied on count=1, so orderBy('id') (creation order) is the stable
        // tie-break — the 11th/12th created players are the ones cut off.
        collect(range(1, 12))->each(function (int $i) use ($game) {
            $player = Player::factory()->create(['team_id' => $game->home_team_id, 'shirt_number' => $i, 'name' => "Ranked Player {$i}"]);
            GameEvent::factory()->for($game)->for($player)->goal()->create();
        });

        $response = $this->get(route('site.scorers'))->assertOk();

        $response->assertSee('Ranked Player 1');
        $response->assertSee('Ranked Player 10');
        $response->assertDontSee('Ranked Player 11');
        $response->assertDontSee('Ranked Player 12');
    }

    public function test_page_renders_with_no_events_recorded(): void
    {
        Season::factory()->create(['is_current' => true]);

        $this->get(route('site.scorers'))->assertOk();
    }
}
