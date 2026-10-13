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

    /**
     * El fallo que el propietario vio el 2026-10-13: un equipo con tres
     * anulados aparecía con ocho partidos jugados en vez de diez.
     *
     * La tabla pedía los partidos filtrando por marcador ANTES de plegarlos, y
     * un anulado sin marcador no llegaba nunca. O sea que un anulado contaba o
     * no contaba según si alguien le había dejado un marcador encima, que es
     * lo contrario de una regla.
     */
    public function test_a_voided_game_counts_as_played_even_with_no_score_on_it(): void
    {
        $game = $this->game();
        $season = $game->matchday->season;

        // El estado roto, escrito sin pasar por el modelo: así llegaron los dos
        // partidos de producción, anulados y editados después.
        $game->voidAgainst('both');
        Game::query()->whereKey($game->getKey())->update(['home_score' => null, 'away_score' => null]);

        $rows = (new StandingsService)->forSeason($season);

        foreach ([$game->home_team_id, $game->away_team_id] as $teamId) {
            $row = $rows->firstWhere(fn ($r) => $r->team->getKey() === $teamId);
            $this->assertSame(1, $row->played, 'Un anulado tiene que contar como jugado');
            $this->assertSame(0, $row->points, 'Y no repartir nada');
            $this->assertSame(0, $row->goals_for);
        }
    }

    /**
     * Y que no vuelva a darse ese estado: el formulario del panel puede mover
     * el marcador y `voided_at` por separado, así que el modelo los reconcilia
     * al guardar. Resuelve en vez de rechazar, como el invariante de Season.
     */
    public function test_saving_a_voided_game_restores_its_regulation_score(): void
    {
        $game = $this->game();
        $game->voidAgainst('both');

        $game->home_score = null;
        $game->away_score = null;
        $game->save();

        $this->assertSame([0, 0], [$game->fresh()->home_score, $game->fresh()->away_score]);
    }

    public function test_the_regulation_score_restored_matches_who_was_penalised(): void
    {
        foreach ([['home', 0, 3], ['away', 3, 0]] as [$contra, $local, $visitante]) {
            $game = $this->game();
            $game->voidAgainst($contra);

            $game->home_score = null;
            $game->away_score = null;
            $game->save();

            $this->assertSame(
                [$local, $visitante],
                [$game->fresh()->home_score, $game->fresh()->away_score],
                "Anulado contra {$contra}",
            );
        }
    }

    /**
     * Un partido que NO está anulado sigue pudiendo quedarse sin marcador: es
     * un partido por jugar y la reconciliación no debe tocarlo.
     */
    public function test_a_game_that_is_not_voided_keeps_its_empty_score(): void
    {
        $game = $this->game();
        $game->home_score = null;
        $game->away_score = null;
        $game->save();

        $this->assertNull($game->fresh()->home_score);
        $this->assertNull($game->fresh()->away_score);
    }
}
