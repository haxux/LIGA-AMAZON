<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anular un partido contra uno o ambos equipos: marcador reglamentario 3-0,
 * sin eventos, y en rojo (sólo) el equipo afectado.
 */
class VoidGameTest extends TestCase
{
    use RefreshDatabase;

    private function game(): Game
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->for($season)->create();

        return Game::factory()->create([
            'matchday_id' => $matchday->id,
            'home_team_id' => Team::factory()->for($season)->create()->id,
            'away_team_id' => Team::factory()->for($season)->create()->id,
        ]);
    }

    public function test_voiding_against_the_home_team_gives_the_away_team_a_three_nil_win_and_drops_events(): void
    {
        $game = $this->game();
        GameEvent::factory()->create(['game_id' => $game->id]);

        $game->voidAgainst('home');
        $game->refresh();

        $this->assertSame([0, 3], [$game->home_score, $game->away_score]);
        $this->assertSame(['home'], $game->penalizedSides());
        $this->assertSame(0, $game->events()->count());
        $this->assertFalse($game->isVoidedWithoutWinner());
    }

    public function test_a_forfeit_awards_points_in_the_table_but_a_double_void_does_not(): void
    {
        $game = $this->game();
        $game->voidAgainst('home');

        $rows = (new StandingsService)->forSeason($game->matchday->season);
        $this->assertSame(3, $rows->firstWhere(fn ($r) => $r->team->is($game->awayTeam))->points);

        $game->voidAgainst('both');
        $rows = (new StandingsService)->forSeason($game->matchday->season);
        $this->assertSame(0, $rows->firstWhere(fn ($r) => $r->team->is($game->awayTeam))->points);
        $this->assertSame(['home', 'away'], $game->fresh()->penalizedSides());
    }

    public function test_only_the_penalised_team_is_red_on_the_public_game_page(): void
    {
        $game = $this->game();
        $game->voidAgainst('away');

        $html = $this->get(route('site.games.show', $game))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'text-rose-500'));
    }

    public function test_unvoiding_returns_the_game_to_pending(): void
    {
        $game = $this->game();
        $game->voidAgainst('both');
        $game->unvoid();

        $this->assertNull($game->fresh()->home_score);
        $this->assertFalse($game->fresh()->isVoided());
        $this->assertSame([], $game->fresh()->penalizedSides());
    }
}
