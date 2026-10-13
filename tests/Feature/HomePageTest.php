<?php

namespace Tests\Feature;

use App\Models\Cup;
use App\Models\CupRound;
use App\Models\CupTie;
use App\Models\Division;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La portada, a la que lleva el botón de la marca desde el 2026-10-13. Hasta
 * entonces la raíz era Clasificación, que se mudó a /clasificacion.
 *
 * El criterio que más vigilan estos tests es el de la Fase 15: un bloque cuyas
 * filas dicen de qué competición son puede mezclar liga y copa, y uno que
 * agrega en UNA cifra no.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private int $nextMatchday = 1;

    /**
     * Una división con dos equipos y un partido jugado 3-0.
     *
     * @return array{0: Division, 1: Team, 2: Team}
     */
    private function division(Season $season, string $name): array
    {
        $division = Division::factory()->create(['season_id' => $season->id, 'name' => $name]);

        $winner = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'name' => $name.' Lider']);
        $loser = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'name' => $name.' Colista']);

        $this->leagueGame($season, $division, $winner, $loser, 3, 0);

        return [$division, $winner, $loser];
    }

    private function leagueGame(Season $season, Division $division, Team $home, Team $away, ?int $hs, ?int $as, ?string $date = null): Game
    {
        $matchday = Matchday::factory()->create([
            'season_id' => $season->id,
            'division_id' => $division->id,
            'number' => $this->nextMatchday++,
            'date' => $date,
        ]);

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => $hs,
            'away_score' => $as,
        ]);
    }

    private function scorer(Game $game, string $name, int $goals, string $type = GameEvent::TYPE_GOAL): Player
    {
        $player = Player::factory()->create([
            'name' => $name,
            'team_id' => $game->home_team_id,
            'position' => $type === GameEvent::TYPE_CLEAN_SHEET ? Player::POSITION_GOALKEEPER : 'Forward',
        ]);

        foreach (range(1, $goals) as $ignored) {
            GameEvent::factory()->for($game)->for($player)->create(['type' => $type]);
        }

        return $player;
    }

    public function test_the_brand_button_leads_to_the_home_page(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $this->division($season, 'Primera');

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('href="'.route('site.home').'"', false);
    }

    /**
     * Clasificación dejó la raíz pero conserva su nombre de ruta, así que todo
     * lo que la enlazaba sigue valiendo.
     */
    public function test_the_standings_moved_to_their_own_address(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $this->division($season, 'Primera');

        $this->assertSame('/clasificacion', route('site.standings', absolute: false));

        $this->get('/clasificacion')->assertOk()->assertSee('Clasificación');
    }

    public function test_the_home_page_welcomes_with_the_season_and_the_progress(): void
    {
        $season = Season::factory()->create(['is_current' => true, 'name' => 'Temporada 2026-I']);
        [$division, $home, $away] = $this->division($season, 'Primera');
        // Uno jugado por la división, más uno sin jugar: 1 de 2.
        $this->leagueGame($season, $division, $home, $away, null, null);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('TEMPORADA 2026-I')
            ->assertSee('LIGA DISPUTADA')
            ->assertSee('1 de 2 partidos');
    }

    /**
     * Con dos divisiones, la jornada 7 son DOS filas de `matchdays`. Enseñar
     * sólo una sería media jornada sin avisar de que falta la otra mitad.
     */
    public function test_the_current_matchday_covers_every_division(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [, $unoA, $unoB] = $this->division($season, 'Primera');
        [, $dosA, $dosB] = $this->division($season, 'Segunda');

        // La misma jornada por venir en las dos divisiones.
        foreach ([['Primera', $unoA, $unoB], ['Segunda', $dosA, $dosB]] as [$nombre, $local, $visitante]) {
            $matchday = Matchday::factory()->create([
                'season_id' => $season->id,
                'division_id' => Division::where('name', $nombre)->where('season_id', $season->id)->value('id'),
                'number' => 99,
                'date' => today()->addDay(),
            ]);
            Game::factory()->for($matchday)->create([
                'home_team_id' => $local->id,
                'away_team_id' => $visitante->id,
            ]);
        }

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('JORNADA 99')
            ->assertSee('JORNADA 99 · PRIMERA')
            ->assertSee('JORNADA 99 · SEGUNDA');
    }

    /**
     * Lo vio el propietario el 2026-10-13: en «se juega ahora» salían partidos
     * con su resultado puesto, repetidos justo al lado en «últimos
     * resultados». Una jornada se juega a trozos, y la portada enseñaba la
     * jornada ENTERA.
     */
    public function test_played_games_never_appear_among_the_upcoming_ones(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [$division, $home, $away] = $this->division($season, 'Primera');

        // Dos equipos propios para el que falta por jugar: si reutilizara los
        // del jugado, sus nombres saldrían en los dos y el test no probaría
        // nada.
        $uno = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'name' => 'Equipo Por Jugar']);
        $dos = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'name' => 'Su Rival']);

        // La misma jornada: uno ya jugado y otro todavía no.
        $matchday = Matchday::factory()->create([
            'season_id' => $season->id, 'division_id' => $division->id,
            'number' => 50, 'date' => today()->addDay(),
        ]);
        Game::factory()->for($matchday)->create([
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
            'home_score' => 2, 'away_score' => 2,
        ]);
        Game::factory()->for($matchday)->create([
            'home_team_id' => $uno->id, 'away_team_id' => $dos->id,
        ]);

        $response = $this->get(route('site.home'))->assertOk();

        $proximos = str($response->getContent())->after('PRÓXIMA JORNADA')->before('ÚLTIMOS RESULTADOS')->toString();
        $this->assertStringContainsString('Equipo Por Jugar', $proximos);
        $this->assertStringNotContainsString('Primera Colista', $proximos);
    }

    /**
     * Un anulado tampoco está por jugar: ya se resolvió, aunque no se jugara.
     */
    public function test_voided_games_never_appear_among_the_upcoming_ones(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [$division, $home, $away] = $this->division($season, 'Primera');

        $anulado = $this->leagueGame($season, $division, $home, $away, null, null, today()->addDay()->toDateString());
        $anulado->voidAgainst('both');

        $response = $this->get(route('site.home'))->assertOk();
        $proximos = str($response->getContent())->after('SE JUEGA AHORA')->before('CÓMO VA LA LIGA')->toString();

        $this->assertStringNotContainsString('PRÓXIMA JORNADA', $proximos);
    }

    /**
     * Un aplazado de una jornada anterior sigue estando por jugar. El rótulo
     * no puede entonces prometer UNA jornada, porque mentiría sobre el otro.
     */
    public function test_the_heading_does_not_name_a_matchday_when_they_are_mixed(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [$division, $home, $away] = $this->division($season, 'Primera');

        $this->leagueGame($season, $division, $home, $away, null, null, today()->addDays(2)->toDateString());
        $this->leagueGame($season, $division, $away, $home, null, null, today()->addDays(9)->toDateString());

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('PRÓXIMOS PARTIDOS')
            ->assertDontSee('PRÓXIMA JORNADA');
    }

    public function test_the_home_page_lists_the_latest_results(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [$division, $home, $away] = $this->division($season, 'Primera');
        $this->leagueGame($season, $division, $home, $away, 5, 4, today()->subDay()->toDateString());

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('ÚLTIMOS RESULTADOS')
            ->assertSee('Primera Lider');
    }

    public function test_the_podium_shows_the_top_of_every_division(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $this->division($season, 'Primera');
        $this->division($season, 'Segunda');

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('Cómo va la liga')
            ->assertSee('Primera Lider')
            ->assertSee('Segunda Lider');
    }

    /**
     * Una división con equipos pero sin un partido jugado no tiene podio: su
     * primero lo sería por orden de id.
     */
    public function test_a_division_with_nothing_played_has_no_podium(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $this->division($season, 'Primera');

        $vacia = Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);
        Team::factory()->create(['season_id' => $season->id, 'division_id' => $vacia->id, 'name' => 'Nunca Jugo']);

        $response = $this->get(route('site.home'))->assertOk();
        $response->assertSee('Primera Lider');
        $response->assertDontSee('Nunca Jugo');
    }

    public function test_the_leaders_name_every_tied_player(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [$division, $home, $away] = $this->division($season, 'Primera');
        $game = $this->leagueGame($season, $division, $home, $away, 4, 0);

        $this->scorer($game, 'Empatado Uno', 2);
        $this->scorer($game, 'Empatado Dos', 2);
        $this->scorer($game, 'Goleador Menor', 1);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('MÁXIMO GOLEADOR · LIGA')
            ->assertSee('Empatado Uno')
            ->assertSee('Empatado Dos')
            ->assertDontSee('Goleador Menor');
    }

    /**
     * Las tres cifras de los líderes no llevan etiqueta de competición, así que
     * son de liga: un goleador de copa no puede colarse entre ellas.
     */
    public function test_cup_goals_stay_out_of_the_leaders(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [$division, $home, $away] = $this->division($season, 'Primera');
        $game = $this->leagueGame($season, $division, $home, $away, 1, 0);
        $this->scorer($game, 'Goleador De Liga', 1);

        $copa = Cup::factory()->create(['season_id' => $season->id]);
        $ronda = CupRound::create(['cup_id' => $copa->id, 'name' => 'Final', 'position' => 1, 'legs' => 1]);
        $cruce = CupTie::create(['cup_round_id' => $ronda->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);

        $cupGame = Game::create([
            'cup_tie_id' => $cruce->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => 9,
            'away_score' => 0,
        ]);

        $this->scorer($cupGame, 'Goleador De Copa', 9);

        $response = $this->get(route('site.home'))->assertOk();

        // Sale en los resultados, que dicen de qué competición son...
        $response->assertSee('Goleador De Liga');
        // ...pero no entre las tres cifras de cabecera.
        $destacan = str($response->getContent())->after('Quién destaca')->before('Para comentar')->toString();
        $this->assertStringContainsString('Goleador De Liga', $destacan);
        $this->assertStringNotContainsString('Goleador De Copa', $destacan);
    }

    public function test_the_form_block_ranks_by_recent_points(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [$division, $fuerte, $flojo] = $this->division($season, 'Primera');

        // El líder gana otro; el colista sigue perdiendo.
        $this->leagueGame($season, $division, $fuerte, $flojo, 2, 1, today()->subDay()->toDateString());

        $response = $this->get(route('site.home'))->assertOk();
        $response->assertSee('En forma');

        $forma = str($response->getContent())->after('En forma')->before('La temporada en cifras')->toString();
        $this->assertStringContainsString('Primera Lider', $forma);
        // Dos victorias seguidas, y la racha se lee con sus letras.
        $this->assertStringContainsString('>V<', $forma);
    }

    public function test_the_season_numbers_come_from_league_games(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        [$division, $home, $away] = $this->division($season, 'Primera');
        $this->leagueGame($season, $division, $home, $away, 4, 1, today()->subDay()->toDateString());

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('La temporada en cifras')
            // 3-0 y 4-1: ocho goles en dos partidos, cuatro de media.
            ->assertSee('GOLES EN LA LIGA')
            ->assertSee('MAYOR GOLEADA');
    }

    public function test_the_home_page_shows_the_latest_news(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $this->division($season, 'Primera');

        News::factory()->create(['title' => 'La Noticia Fresca', 'published_at' => now()->subHour()]);
        News::factory()->create(['title' => 'Un Borrador', 'published_at' => null]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('La Noticia Fresca')
            ->assertDontSee('Un Borrador');
    }

    /**
     * La raíz llevaba `?temporada` cuando era Clasificación, así que los
     * marcadores con ese parámetro tienen que seguir llegando a Historia.
     */
    public function test_a_legacy_season_parameter_on_the_root_reaches_history(): void
    {
        $current = Season::factory()->create(['is_current' => true]);
        $this->division($current, 'Primera');
        $old = Season::factory()->create(['is_current' => false]);

        $this->get('/?temporada='.$old->id)
            ->assertRedirect(route('site.history', ['temporada' => $old->id]));
    }

    public function test_an_invented_season_parameter_stays_on_the_home_page(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $this->division($season, 'Primera');

        $this->get('/?temporada=9999')->assertOk()->assertSee('Primera Lider');
    }

    /**
     * Una temporada recién abierta no tiene nada que resumir, y la portada tiene
     * que seguir siendo una página en vez de reventar o quedarse en blanco.
     */
    public function test_the_home_page_survives_a_season_with_nothing_played(): void
    {
        Season::factory()->create(['is_current' => true, 'name' => 'Temporada Nueva']);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('TEMPORADA NUEVA')
            ->assertSee('Quién destaca')
            ->assertSee('Sin datos todavía');
    }
}
