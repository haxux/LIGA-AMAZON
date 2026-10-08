<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Division;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Stadium;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El detalle de un partido: sus eventos, y cuando no los tiene, su resultado.
 */
class GamePageTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Division $division;

    private Team $home;

    private Team $away;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);
        $this->home = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $this->division->id, 'name' => 'Manaos FC']);
        $this->away = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $this->division->id, 'name' => 'Tapajós SC']);
    }

    private function game(?int $homeScore = 2, ?int $awayScore = 1, int $number = 1): Game
    {
        $matchday = Matchday::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
            'number' => $number,
        ]);

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);
    }

    private function squadPlayer(Team $team, string $name, string $position = 'Forward'): Player
    {
        $player = Player::factory()->create([
            'club_id' => $team->club_id,
            'name' => $name,
            'position' => $position,
        ]);

        SquadMembership::factory()->create(['team_id' => $team->id, 'player_id' => $player->id]);

        return $player;
    }

    public function test_the_detail_shows_the_teams_the_result_and_the_matchday(): void
    {
        $game = $this->game();

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertSee('Tapajós SC')
            // La metadata del sitio va en mayúsculas, como en clasificación y
            // partidos: el estilo es del layout, no de esta página.
            ->assertSee('PRIMERA')
            ->assertSee('JORNADA 1');
    }

    /**
     * El minuto dejó de registrarse (decisión del propietario): en su sitio va
     * un icono, y el nombre del evento sigue escrito debajo del jugador.
     */
    public function test_it_lists_the_events_with_an_icon_instead_of_a_minute(): void
    {
        $game = $this->game();
        $scorer = $this->squadPlayer($this->home, 'Goleador Local');
        $assister = $this->squadPlayer($this->away, 'Asistente Visitante');

        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $scorer->id, 'type' => GameEvent::TYPE_GOAL]);
        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $assister->id, 'type' => GameEvent::TYPE_ASSIST]);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('Goleador Local')
            ->assertSee('Asistente Visitante')
            ->assertSee('Gol')
            ->assertSee('Asistencia')
            // El icono lleva su nombre, que es lo que lee un lector de pantalla.
            ->assertSee('aria-label="Gol"', false)
            ->assertSee('aria-label="Asistencia"', false);
    }

    public function test_no_minute_is_shown_even_when_the_column_still_holds_one(): void
    {
        $game = $this->game();
        $scorer = $this->squadPlayer($this->home, 'Goleador Local');

        GameEvent::factory()->create([
            'game_id' => $game->id,
            'player_id' => $scorer->id,
            'type' => GameEvent::TYPE_GOAL,
            'minute' => 23,
        ]);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertDontSee("23'");
    }

    /**
     * Por orden de CARGA, que desde que no hay minuto es el único orden que
     * significa algo: el relato del partido tal como lo apuntó el operador.
     */
    public function test_the_events_read_in_the_order_they_were_entered(): void
    {
        $game = $this->game();
        $primero = $this->squadPlayer($this->home, 'Gol Primero');
        $segundo = $this->squadPlayer($this->home, 'Gol Segundo');

        // Con el minuto al revés del orden de carga, para que sólo pueda pasar
        // si manda la carga.
        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $primero->id, 'type' => GameEvent::TYPE_GOAL, 'minute' => 88]);
        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $segundo->id, 'type' => GameEvent::TYPE_GOAL, 'minute' => 5]);

        $this->get(route('site.games.show', $game))
            ->assertSeeInOrder(['Gol Primero', 'Gol Segundo']);
    }

    /**
     * Dos amarillas al mismo jugador son una expulsión, y se deduce de ellas en
     * vez de registrarse aparte.
     */
    public function test_a_second_yellow_reads_as_a_sending_off(): void
    {
        $game = $this->game();
        $expulsado = $this->squadPlayer($this->home, 'Doble Amarilla');

        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $expulsado->id, 'type' => GameEvent::TYPE_YELLOW_CARD]);
        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $expulsado->id, 'type' => GameEvent::TYPE_YELLOW_CARD]);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('Tarjeta amarilla')
            ->assertSee('Doble amarilla · expulsado')
            ->assertSee('aria-label="Doble amarilla: expulsado"', false);
    }

    public function test_a_direct_red_is_not_read_as_a_second_yellow(): void
    {
        $game = $this->game();
        $expulsado = $this->squadPlayer($this->home, 'Roja Directa');

        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $expulsado->id, 'type' => GameEvent::TYPE_RED_CARD]);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('aria-label="Tarjeta roja directa"', false)
            ->assertDontSee('Doble amarilla');
    }

    /**
     * La portería a cero es una cifra de temporada, no un momento del
     * partido: se cuenta en las estadísticas del club y del jugador, pero no
     * aparece entre los eventos de este detalle.
     */
    public function test_a_clean_sheet_does_not_show_in_the_match_detail(): void
    {
        $game = $this->game(1, 0);
        $keeper = $this->squadPlayer($this->home, 'Portero Local', Player::POSITION_GOALKEEPER);

        GameEvent::factory()->create([
            'game_id' => $game->id,
            'player_id' => $keeper->id,
            'type' => GameEvent::TYPE_CLEAN_SHEET,
            'minute' => null,
        ]);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertDontSee('Portería a cero')
            ->assertSee('Sin eventos registrados');
    }

    /**
     * La asistencia de un gol se enseña debajo de él y no como fila propia.
     */
    public function test_a_goals_assist_shows_nested_under_it(): void
    {
        $game = $this->game();
        $scorer = $this->squadPlayer($this->home, 'Goleador Local');
        $assister = $this->squadPlayer($this->home, 'Asistente Local');

        $goal = GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $scorer->id, 'type' => GameEvent::TYPE_GOAL, 'minute' => 40]);
        GameEvent::factory()->create([
            'game_id' => $game->id,
            'player_id' => $assister->id,
            'type' => GameEvent::TYPE_ASSIST,
            'minute' => 40,
            'related_event_id' => $goal->id,
        ]);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('Goleador Local')
            ->assertSeeInOrder(['Goleador Local', 'Asistente Local']);
    }

    public function test_a_game_without_events_shows_only_its_result(): void
    {
        $game = $this->game(3, 0);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('3')
            ->assertSee('0')
            ->assertSee('Sin eventos registrados');
    }

    public function test_an_unplayed_game_says_so_instead_of_showing_a_score(): void
    {
        $game = $this->game(null, null);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('POR JUGAR');
    }

    /**
     * El estadio es del club local, y sobrevive a las temporadas como él.
     */
    public function test_it_names_the_home_clubs_stadium_when_there_is_one(): void
    {
        $game = $this->game();
        Stadium::factory()->create(['club_id' => $this->home->club_id, 'name' => 'Estadio del Río']);

        $this->get(route('site.games.show', $game))->assertSee('ESTADIO DEL RÍO');
    }

    /**
     * Desde cualquier sitio donde haya una tarjeta de partido se llega al
     * detalle: es la misma tarjeta en las tres pantallas que la usan.
     */
    public function test_the_game_cards_link_to_the_detail(): void
    {
        $game = $this->game();
        $url = route('site.games.show', $game);

        $this->get(route('site.fixtures'))->assertSee($url, escape: false);

        $club = Club::query()->whereKey($this->home->club_id)->first();

        $this->get(route('site.clubs.show', ['club' => $club->id, 'tab' => 'partidos']))
            ->assertSee($url, escape: false);
    }

    public function test_a_game_that_does_not_exist_is_a_404(): void
    {
        $this->get('/partidos/999999')->assertNotFound();
    }
}
