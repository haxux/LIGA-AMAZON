<?php

namespace Tests\Feature;

use App\Models\Cup;
use App\Models\Division;
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

    // ── Filtros por división y jornada ──────────────────────────────────────

    /**
     * Un partido en la división y la jornada pedidas, con su goleador.
     */
    private function golEn(Season $season, Division $division, int $jornada, string $goleador): Player
    {
        $matchday = Matchday::factory()->create([
            'season_id' => $season->id,
            'division_id' => $division->id,
            'number' => $jornada,
        ]);

        $game = Game::factory()->for($matchday)->create([
            'home_team_id' => Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id])->id,
            'away_team_id' => Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id])->id,
        ]);

        $jugador = Player::factory()->create(['name' => $goleador]);
        GameEvent::factory()->for($game)->for($jugador)->goal()->create();

        return $jugador;
    }

    public function test_the_division_filter_narrows_the_board(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        $segunda = Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);

        $this->golEn($season, $primera, 1, 'Goleador De Primera');
        $this->golEn($season, $segunda, 1, 'Goleador De Segunda');

        $this->get('/estadisticas')
            ->assertOk()
            ->assertSee('Goleador De Primera')
            ->assertSee('Goleador De Segunda');

        $this->get('/estadisticas?division='.$primera->id)
            ->assertOk()
            ->assertSee('Goleador De Primera')
            ->assertDontSee('Goleador De Segunda');
    }

    public function test_the_matchday_filter_narrows_the_board(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);

        $this->golEn($season, $division, 1, 'Gol De La Uno');
        $this->golEn($season, $division, 7, 'Gol De La Siete');

        $this->get('/estadisticas?fecha=7')
            ->assertOk()
            ->assertSee('Gol De La Siete')
            ->assertDontSee('Gol De La Uno');
    }

    /**
     * Las URLs públicas las escribe cualquiera: una división o una jornada que
     * no existen caen en «todas», igual que una competición inventada.
     */
    public function test_invented_filter_values_fall_back_to_everything(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $this->golEn($season, $division, 1, 'Sigo Saliendo');

        $this->get('/estadisticas?division=99999&fecha=12345')
            ->assertOk()
            ->assertSee('Sigo Saliendo');
    }

    /**
     * División y jornada son cosas de la liga: un partido de copa no cuelga de
     * ninguna. Pedir una copa Y una división a la vez no deja nada, y eso es lo
     * correcto — pero tiene que quedarse vacío, no reventar.
     */
    public function test_asking_for_a_cup_and_a_division_at_once_is_empty_but_not_broken(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $this->golEn($season, $division, 1, 'Goleador De Liga');

        $copa = Cup::factory()->create(['season_id' => $season->id]);

        $this->get('/estadisticas?competicion=copa:'.$copa->id.'&division='.$division->id)
            ->assertOk()
            ->assertDontSee('Goleador De Liga')
            ->assertSee('Sin datos todavía');
    }

    /**
     * El avatar de las listas era un círculo gris fijo desde la Fase 6, a la
     * espera de una fuente de imágenes que nunca llegó. Ahora enseña la foto
     * que el administrador sube a mano, y sigue cayendo en el círculo cuando no
     * la hay.
     */
    public function test_the_scorer_list_shows_the_photo_when_there_is_one(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $jugador = $this->golEn($season, $division, 1, 'Con Retrato');

        $this->get('/estadisticas')->assertOk()->assertDontSee('players/cara.png');

        $jugador->update(['photo_path' => 'players/cara.png']);

        $this->get('/estadisticas')->assertOk()->assertSee('players/cara.png');
    }
}
