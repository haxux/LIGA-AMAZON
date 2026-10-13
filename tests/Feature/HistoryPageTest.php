<?php

namespace Tests\Feature;

use App\Models\Cup;
use App\Models\CupRound;
use App\Models\CupTie;
use App\Models\Division;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historia: las clasificaciones de las temporadas ya cerradas, que es donde se
 * fue el filtro que Clasificación tenía.
 *
 * Lo que vigilan estos tests sobre todo es el umbral del archivo. En la base
 * real existen dos temporadas de 2025 completamente vacías —cero divisiones,
 * cero jornadas, cero partidos— y ofrecerlas en el desplegable sería prometer
 * una tabla que al elegirla sale a ceros.
 */
class HistoryPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Una temporada cerrada de verdad: una división, dos equipos y un partido
     * con marcador. El marcador es lo que la mete en el archivo.
     *
     * @return array{0: Season, 1: Division, 2: Team, 3: Team}
     */
    private function closedSeason(string $name, int $startYear, string $division = 'Primera'): array
    {
        $season = Season::factory()->create([
            'is_current' => false,
            'name' => $name,
            'start_date' => $startYear.'-08-01',
            'end_date' => ($startYear + 1).'-05-31',
        ]);

        return [$season, ...$this->playedDivision($season, $division, $name)];
    }

    /**
     * @return array{0: Division, 1: Team, 2: Team}
     */
    private function playedDivision(Season $season, string $name, string $prefix): array
    {
        $division = Division::factory()->create(['season_id' => $season->id, 'name' => $name]);

        $winner = Team::factory()->create([
            'season_id' => $season->id, 'division_id' => $division->id, 'name' => $prefix.' Campeon',
        ]);
        $runnerUp = Team::factory()->create([
            'season_id' => $season->id, 'division_id' => $division->id, 'name' => $prefix.' Segundo',
        ]);

        $matchday = Matchday::factory()->create([
            'season_id' => $season->id, 'division_id' => $division->id, 'number' => 1,
        ]);

        Game::factory()->for($matchday)->create([
            'home_team_id' => $winner->id,
            'away_team_id' => $runnerUp->id,
            'home_score' => 3,
            'away_score' => 0,
        ]);

        return [$division, $winner, $runnerUp];
    }

    /**
     * Cada llamada estrena jornada: (temporada, división, número) es único, así
     * que dos goleadores en la misma división no pueden compartirla.
     */
    private int $nextMatchday = 2;

    private function goalsFor(Season $season, Division $division, string $player, int $howMany): Player
    {
        $matchday = Matchday::factory()->create([
            'season_id' => $season->id, 'division_id' => $division->id, 'number' => $this->nextMatchday++,
        ]);

        $game = Game::factory()->for($matchday)->played()->create([
            'home_team_id' => Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id])->id,
            'away_team_id' => Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id])->id,
        ]);

        $jugador = Player::factory()->create(['name' => $player]);

        foreach (range(1, $howMany) as $ignored) {
            GameEvent::factory()->for($game)->for($jugador)->goal()->create();
        }

        return $jugador;
    }

    public function test_history_says_so_when_nothing_is_closed_yet(): void
    {
        $current = Season::factory()->create(['is_current' => true]);
        $this->playedDivision($current, 'Primera', 'Vigente');

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('Todavía no hay temporadas cerradas.')
            ->assertDontSee('name="temporada"', false);
    }

    public function test_the_standings_hide_the_history_button_until_there_is_something_behind_it(): void
    {
        $current = Season::factory()->create(['is_current' => true]);
        $this->playedDivision($current, 'Primera', 'Vigente');

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertDontSee(route('site.history'));

        $this->closedSeason('2025/26', 2025);

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee(route('site.history'));
    }

    /**
     * El umbral: una temporada vacía no entra, aunque exista. Son las dos de
     * 2025 de la base real.
     */
    public function test_only_seasons_that_were_actually_played_are_offered(): void
    {
        Season::factory()->create(['is_current' => true]);
        Season::factory()->create(['is_current' => false, 'name' => 'Temporada Vacia', 'start_date' => '2024-08-01']);
        $this->closedSeason('Temporada Jugada', 2025);

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('Temporada Jugada')
            ->assertDontSee('Temporada Vacia');
    }

    /**
     * Una temporada con equipos y jornadas pero sin un marcador puesto tampoco
     * entra: su tabla saldría a ceros.
     */
    public function test_a_season_whose_games_were_never_played_is_not_offered(): void
    {
        Season::factory()->create(['is_current' => true]);

        $sinJugar = Season::factory()->create(['is_current' => false, 'name' => 'Temporada Sin Jugar', 'start_date' => '2024-08-01']);
        $division = Division::factory()->create(['season_id' => $sinJugar->id]);
        $matchday = Matchday::factory()->create(['season_id' => $sinJugar->id, 'division_id' => $division->id]);
        Game::factory()->for($matchday)->create([
            'home_team_id' => Team::factory()->create(['season_id' => $sinJugar->id, 'division_id' => $division->id])->id,
            'away_team_id' => Team::factory()->create(['season_id' => $sinJugar->id, 'division_id' => $division->id])->id,
        ]);

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('Todavía no hay temporadas cerradas.')
            ->assertDontSee('Temporada Sin Jugar');
    }

    public function test_the_active_season_is_never_in_history(): void
    {
        $current = Season::factory()->create(['is_current' => true, 'name' => 'Temporada Vigente']);
        $this->playedDivision($current, 'Primera', 'Vigente');
        $this->closedSeason('Temporada Cerrada', 2025);

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('Temporada Cerrada')
            ->assertDontSee('Temporada Vigente');
    }

    /**
     * Sin marca de vigente, la activa es la última por id. Si el archivo mirara
     * sólo `is_current`, ésa saldría en las dos páginas a la vez.
     */
    public function test_with_no_season_flagged_current_the_latest_is_still_excluded(): void
    {
        $this->closedSeason('La Vieja', 2024);
        $laUltima = Season::factory()->create(['is_current' => false, 'name' => 'La Ultima', 'start_date' => '2025-08-01']);
        $this->playedDivision($laUltima, 'Primera', 'Ultima');

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('La Vieja')
            ->assertDontSee('La Ultima');
    }

    public function test_history_defaults_to_the_most_recent_closed_season(): void
    {
        Season::factory()->create(['is_current' => true]);
        $this->closedSeason('Temporada Antigua', 2023, 'Antigua');
        $this->closedSeason('Temporada Reciente', 2025, 'Reciente');

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('Temporada Reciente Campeon')
            ->assertDontSee('Temporada Antigua Campeon');
    }

    public function test_a_past_season_can_be_chosen_from_the_filter(): void
    {
        Season::factory()->create(['is_current' => true]);
        [$antigua] = $this->closedSeason('Temporada Antigua', 2023, 'Antigua');
        $this->closedSeason('Temporada Reciente', 2025, 'Reciente');

        $this->get(route('site.history', ['temporada' => $antigua->id]))
            ->assertOk()
            ->assertSee('Temporada Antigua Campeon')
            ->assertDontSee('Temporada Reciente Campeon');
    }

    public function test_an_unknown_season_falls_back_to_the_newest_closed_one(): void
    {
        Season::factory()->create(['is_current' => true]);
        $this->closedSeason('Temporada Reciente', 2025, 'Reciente');

        $this->get(route('site.history', ['temporada' => 9999]))
            ->assertOk()
            ->assertSee('Temporada Reciente Campeon');
    }

    public function test_history_names_the_champion_of_every_division(): void
    {
        Season::factory()->create(['is_current' => true]);
        [$season] = $this->closedSeason('Temporada Cerrada', 2025, 'Primera');
        $this->playedDivision($season, 'Segunda', 'Segunda');

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('CAMPEONES')
            ->assertSee('Temporada Cerrada Campeon')
            ->assertSee('Segunda Campeon');
    }

    /**
     * Una división con equipos pero sin un partido jugado no tiene campeón: el
     * primero de su tabla lo sería por orden de id, no por puntos.
     */
    public function test_a_division_with_no_played_games_has_no_champion(): void
    {
        Season::factory()->create(['is_current' => true]);
        [$season] = $this->closedSeason('Temporada Cerrada', 2025, 'Primera');

        $vacia = Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);
        Team::factory()->create(['season_id' => $season->id, 'division_id' => $vacia->id, 'name' => 'Nunca Jugo']);

        $response = $this->get(route('site.history'))->assertOk();

        // Sale en su tabla, pero no en el palmarés.
        $response->assertSee('Nunca Jugo');
        $response->assertSee('CAMPEÓN');
        $this->assertStringNotContainsString(
            'Nunca Jugo',
            str($response->getContent())->before('</section>')->toString(),
        );
    }

    public function test_history_names_the_top_scorer(): void
    {
        Season::factory()->create(['is_current' => true]);
        [$season, $division] = $this->closedSeason('Temporada Cerrada', 2025);
        $this->goalsFor($season, $division, 'Pichichi Unico', 3);
        $this->goalsFor($season, $division, 'Goleador Menor', 1);

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('MÁXIMO GOLEADOR')
            ->assertSee('Pichichi Unico');
    }

    /**
     * Dos que marcaron lo mismo son los dos máximos goleadores. Elegir a uno
     * sería inventar un desempate que la liga no tiene.
     */
    public function test_every_tied_top_scorer_is_named(): void
    {
        Season::factory()->create(['is_current' => true]);
        [$season, $division] = $this->closedSeason('Temporada Cerrada', 2025);
        $this->goalsFor($season, $division, 'Empatado Uno', 2);
        $this->goalsFor($season, $division, 'Empatado Dos', 2);
        $this->goalsFor($season, $division, 'Goleador Menor', 1);

        $response = $this->get(route('site.history'))->assertOk();
        $response->assertSee('MÁXIMOS GOLEADORES');
        $response->assertSee('Empatado Uno');
        $response->assertSee('Empatado Dos');

        $palmares = str($response->getContent())->before('</section>')->toString();
        $this->assertStringNotContainsString('Goleador Menor', $palmares);
    }

    /**
     * El goleador del palmarés es el de la LIGA. En esta página el campeón sale
     * de la tabla de liga, y dos cifras juntas que vinieran de torneos
     * distintos se leerían como si fueran del mismo.
     */
    public function test_cup_goals_do_not_count_towards_the_top_scorer(): void
    {
        Season::factory()->create(['is_current' => true]);
        [$season, $division] = $this->closedSeason('Temporada Cerrada', 2025);
        $this->goalsFor($season, $division, 'Goleador De Liga', 1);

        $copa = Cup::factory()->create(['season_id' => $season->id]);
        $ronda = CupRound::create(['cup_id' => $copa->id, 'name' => 'Final', 'position' => 1, 'legs' => 1]);
        $uno = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id]);
        $dos = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id]);
        $cruce = CupTie::create(['cup_round_id' => $ronda->id, 'home_team_id' => $uno->id, 'away_team_id' => $dos->id]);

        $juegoDeCopa = Game::create([
            'cup_tie_id' => $cruce->id,
            'home_team_id' => $uno->id,
            'away_team_id' => $dos->id,
            'home_score' => 9,
            'away_score' => 0,
        ]);

        $artillero = Player::factory()->create(['name' => 'Goleador De Copa']);

        foreach (range(1, 9) as $ignored) {
            GameEvent::factory()->for($juegoDeCopa)->for($artillero)->goal()->create();
        }

        $response = $this->get(route('site.history'))->assertOk();
        $palmares = str($response->getContent())->before('</section>')->toString();

        $this->assertStringContainsString('Goleador De Liga', $palmares);
        $this->assertStringNotContainsString('Goleador De Copa', $palmares);
    }

    public function test_history_draws_the_full_table_of_the_chosen_season(): void
    {
        Season::factory()->create(['is_current' => true]);
        $this->closedSeason('Temporada Cerrada', 2025, 'Primera');

        $this->get(route('site.history'))
            ->assertOk()
            ->assertSee('Primera')
            ->assertSee('Temporada Cerrada Campeon')
            ->assertSee('Temporada Cerrada Segundo');
    }
}
