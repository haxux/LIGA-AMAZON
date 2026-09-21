<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Division;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Lineup;
use App\Models\LineupSlot;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Trophy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La ficha pública de un club (Fase 11) y sus seis pestañas.
 */
class ClubProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Division $division;

    private Club $club;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);
        $this->club = Club::factory()->create(['name' => 'Manaos FC', 'founded_year' => 1975]);
        $this->team = Team::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
            'club_id' => $this->club->id,
        ]);
    }

    private function url(?string $tab = null, ?int $seasonId = null): string
    {
        return route('site.clubs.show', array_filter([
            'club' => $this->club->id,
            'tab' => $tab,
            'temporada' => $seasonId,
        ]));
    }

    /**
     * Siempre con nombre propio: `ClubFactory` sortea el suyo de un repertorio
     * de diez, así que dejarlo al azar choca una de cada diez veces contra el
     * club que monta `setUp()`.
     */
    private function rival(?string $name = null): Team
    {
        static $sequence = 0;

        return Team::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
            'name' => $name ?? 'Rival '.++$sequence,
        ]);
    }

    private function game(Team $rival, ?int $home = null, ?int $away = null, int $number = 1, bool $atHome = true): Game
    {
        $matchday = Matchday::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
            'number' => $number,
        ]);

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $atHome ? $this->team->id : $rival->id,
            'away_team_id' => $atHome ? $rival->id : $this->team->id,
            'home_score' => $home,
            'away_score' => $away,
        ]);
    }

    private function squadPlayer(array $attributes = [], ?int $shirtNumber = null): Player
    {
        $player = Player::factory()->create(['club_id' => $this->club->id] + $attributes);

        SquadMembership::factory()->create(array_filter([
            'team_id' => $this->team->id,
            'player_id' => $player->id,
            // El factory reparte dorsal si no se le pide uno; pasarle null lo
            // haría chocar contra el NOT NULL de la columna.
            'shirt_number' => $shirtNumber,
        ], fn ($value) => $value !== null));

        return $player;
    }

    public function test_the_profile_opens_on_the_general_tab(): void
    {
        $this->get($this->url())
            ->assertOk()
            ->assertSee('Manaos FC')
            ->assertSee('1975')
            ->assertSee('PRÓXIMO PARTIDO')
            ->assertSee('ONCE IDEAL');
    }

    public function test_every_tab_answers_on_its_own_route(): void
    {
        foreach (['general', 'partidos', 'jugadores', 'trofeos', 'stats', 'tecnico'] as $tab) {
            $this->get($this->url($tab))->assertOk();
        }
    }

    /**
     * Una pestaña inventada cae en General, como una ?jornada inexistente cae
     * en la jornada en curso: la URL de un sitio público la escribe cualquiera.
     */
    public function test_an_unknown_tab_falls_back_to_general(): void
    {
        $this->get($this->url('inventada'))
            ->assertOk()
            ->assertSee('PRÓXIMO PARTIDO');
    }

    /**
     * La ficha es del club, no de su participación de este año: sin inscripción
     * la página sigue en pie, y lo dice.
     */
    public function test_a_club_not_enrolled_this_season_still_has_a_profile(): void
    {
        $other = Club::factory()->create(['name' => 'Tapajós SC']);

        $this->get(route('site.clubs.show', ['club' => $other->id]))
            ->assertOk()
            ->assertSee('Tapajós SC')
            ->assertSee('todavía no ha jugado ninguna temporada');
    }

    public function test_the_season_selector_only_offers_the_seasons_the_club_played(): void
    {
        $other = Season::factory()->create(['name' => '2020/21']);
        Team::factory()->create(['season_id' => $other->id, 'name' => 'Club Ajeno']);

        $this->get($this->url())
            ->assertSee('2026/27')
            ->assertDontSee('2020/21');
    }

    public function test_general_shows_the_next_game_the_form_and_the_position(): void
    {
        $rival = $this->rival('Tapajós SC');
        $this->game($rival, home: 3, away: 0, number: 1);
        $this->game($rival, number: 2);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('Tapajós SC')
            ->assertSee('3–0')
            // Único equipo con partidos ganados de su división: primero.
            ->assertSee('EN LA TABLA');
    }

    public function test_general_names_the_clubs_top_scorer_and_assister(): void
    {
        $scorer = $this->squadPlayer(['name' => 'Goleador Local']);
        $assister = $this->squadPlayer(['name' => 'Asistente Local']);
        $game = $this->game($this->rival(), home: 1, away: 0);

        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $scorer->id, 'type' => GameEvent::TYPE_GOAL]);
        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $assister->id, 'type' => GameEvent::TYPE_ASSIST]);

        $this->get($this->url())
            ->assertSee('Goleador Local')
            ->assertSee('Asistente Local');
    }

    /**
     * El goleador de otro club no es el de este, aunque marque en el mismo
     * partido: el puente es la pertenencia a la plantilla de la temporada.
     */
    public function test_general_ignores_goals_scored_by_the_rival(): void
    {
        $rival = $this->rival('Tapajós SC');
        $game = $this->game($rival, home: 1, away: 1);
        $theirs = Player::factory()->create(['name' => 'Goleador Rival', 'club_id' => $rival->club_id]);
        SquadMembership::factory()->create(['team_id' => $rival->id, 'player_id' => $theirs->id]);

        GameEvent::factory()->create(['game_id' => $game->id, 'player_id' => $theirs->id, 'type' => GameEvent::TYPE_GOAL]);

        $this->get($this->url())->assertDontSee('Goleador Rival');
    }

    public function test_general_draws_the_starting_eleven(): void
    {
        $player = $this->squadPlayer(['name' => 'Once Titular'], shirtNumber: 10);
        $lineup = Lineup::factory()->create(['team_id' => $this->team->id, 'formation' => '4-3-3']);
        LineupSlot::create(['lineup_id' => $lineup->id, 'player_id' => $player->id, 'slot' => 1]);

        $this->get($this->url())
            ->assertSee('4-3-3')
            ->assertSee('Once Titular');
    }

    public function test_partidos_lists_the_clubs_games_by_matchday(): void
    {
        $rival = $this->rival('Tapajós SC');
        $this->game($rival, home: 2, away: 1, number: 1);
        $this->game($rival, number: 2, atHome: false);

        $this->get($this->url('partidos'))
            ->assertOk()
            ->assertSee('Jornada 1')
            ->assertSee('Jornada 2')
            ->assertSee('Tapajós SC');
    }

    public function test_jugadores_groups_the_squad_by_general_position(): void
    {
        $this->squadPlayer(['name' => 'Portero Uno', 'position' => Player::POSITION_GOALKEEPER, 'specific_position' => 'POR'], shirtNumber: 1);
        $this->squadPlayer(['name' => 'Lateral Dos', 'position' => 'Defender', 'specific_position' => 'LI'], shirtNumber: 2);

        $this->get($this->url('jugadores'))
            ->assertOk()
            ->assertSee('Porteros')
            ->assertSee('Defensas')
            ->assertSee('Portero Uno')
            ->assertSee('Lateral Dos')
            ->assertSee('POR')
            ->assertSee('LI');
    }

    public function test_jugadores_shows_only_this_seasons_squad(): void
    {
        $this->squadPlayer(['name' => 'Jugador de Hoy']);
        Player::factory()->create(['name' => 'Jugador sin Ficha', 'club_id' => $this->club->id]);

        $this->get($this->url('jugadores'))
            ->assertSee('Jugador de Hoy')
            ->assertDontSee('Jugador sin Ficha');
    }

    /**
     * Un título se gana una vez y se exhibe siempre, así que la pestaña no
     * obedece al selector de temporada.
     */
    public function test_trofeos_shows_the_whole_palmares(): void
    {
        $past = Season::factory()->create(['name' => '2019/20']);
        Trophy::factory()->create(['club_id' => $this->club->id, 'season_id' => $past->id, 'name' => 'Copa Amazonas']);
        Trophy::factory()->create(['club_id' => $this->club->id, 'season_id' => $this->season->id, 'name' => 'Liga Amazon']);

        $this->get($this->url('trofeos'))
            ->assertOk()
            ->assertSee('Copa Amazonas')
            ->assertSee('Liga Amazon')
            ->assertSee('2019/20');
    }

    public function test_trofeos_does_not_show_another_clubs_titles(): void
    {
        $other = Club::factory()->create();
        Trophy::factory()->create(['club_id' => $other->id, 'season_id' => $this->season->id, 'name' => 'Copa Ajena']);

        $this->get($this->url('trofeos'))->assertDontSee('Copa Ajena');
    }

    public function test_stats_are_derived_from_games_and_events(): void
    {
        $rival = $this->rival();
        $won = $this->game($rival, home: 3, away: 1, number: 1);
        $this->game($rival, home: 0, away: 0, number: 2);

        $keeper = $this->squadPlayer(['position' => Player::POSITION_GOALKEEPER]);
        $booked = $this->squadPlayer(['position' => 'Defender']);
        GameEvent::factory()->create(['game_id' => $won->id, 'player_id' => $booked->id, 'type' => GameEvent::TYPE_YELLOW_CARD]);
        GameEvent::factory()->create(['game_id' => $won->id, 'player_id' => $keeper->id, 'type' => GameEvent::TYPE_CLEAN_SHEET, 'minute' => null]);

        $response = $this->get($this->url('stats'))->assertOk();

        $response->assertSee('PJ')->assertSee('PORTERÍAS A CERO')->assertSee('AMARILLAS');
        $response->assertSeeInOrder(['PJ', '2']);
    }

    public function test_tecnico_names_the_coach_of_the_club(): void
    {
        User::factory()->coachOf($this->club)->create(['name' => 'Ana Entrenadora']);

        $this->get($this->url('tecnico'))
            ->assertOk()
            ->assertSee('Ana Entrenadora')
            ->assertSee('DIRECTOR TÉCNICO');
    }

    public function test_tecnico_says_so_when_there_is_none(): void
    {
        $this->get($this->url('tecnico'))
            ->assertOk()
            ->assertSee('no tiene director técnico asignado');
    }
}
