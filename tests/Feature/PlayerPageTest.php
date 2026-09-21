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
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La ficha pública de un jugador: quién es y lo que lleva hecho, derivado de
 * `game_events` (sin tabla de estadísticas, como todo lo demás).
 */
class PlayerPageTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Division $division;

    private Club $club;

    private Team $team;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);
        $this->club = Club::factory()->create(['name' => 'Manaos FC']);
        $this->team = Team::factory()->create([
            'club_id' => $this->club->id,
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
        ]);

        $this->player = Player::factory()->create([
            'club_id' => $this->club->id,
            'name' => 'Rivaldo Nunes',
            'position' => 'Forward',
            'specific_position' => 'DC',
            'birth_date' => '1998-04-12',
            'market_value' => 750_000,
        ]);

        SquadMembership::factory()->create([
            'team_id' => $this->team->id,
            'player_id' => $this->player->id,
            'shirt_number' => 9,
        ]);
    }

    private function game(?Team $team = null, ?Season $season = null, int $number = 1): Game
    {
        $team ??= $this->team;
        $season ??= $this->season;

        $matchday = Matchday::factory()->create([
            'season_id' => $season->id,
            'division_id' => $team->division_id ?? $this->division->id,
            'number' => $number,
        ]);

        $rival = Team::factory()->create([
            'season_id' => $season->id,
            'division_id' => $matchday->division_id,
            'name' => 'Rival '.$number.' '.$season->id,
        ]);

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $team->id,
            'away_team_id' => $rival->id,
            'home_score' => 2,
            'away_score' => 1,
        ]);
    }

    private function event(string $type, ?int $minute = 20, ?Game $game = null, ?Player $player = null): GameEvent
    {
        return GameEvent::factory()->create([
            'game_id' => ($game ?? $this->game())->id,
            'player_id' => ($player ?? $this->player)->id,
            'type' => $type,
            'minute' => $type === GameEvent::TYPE_CLEAN_SHEET ? null : $minute,
        ]);
    }

    public function test_the_page_shows_who_the_player_is(): void
    {
        $this->get(route('site.players.show', $this->player))
            ->assertOk()
            ->assertSee('Rivaldo Nunes')
            ->assertSee('MANAOS FC')
            ->assertSee('DELANTERO')
            ->assertSee('DC')
            ->assertSee('9')
            // Valor con separador de miles y sin símbolo de moneda (Fase 12).
            ->assertSee('750.000');
    }

    public function test_it_counts_goals_assists_and_cards_of_the_season(): void
    {
        $game = $this->game();

        $this->event(GameEvent::TYPE_GOAL, 12, $game);
        $this->event(GameEvent::TYPE_GOAL, 55, $game);
        $this->event(GameEvent::TYPE_ASSIST, 70, $game);
        $this->event(GameEvent::TYPE_YELLOW_CARD, 80, $game);

        $response = $this->get(route('site.players.show', $this->player))->assertOk();

        $response->assertSeeInOrder(['GOLES', '2']);
        $response->assertSeeInOrder(['ASISTENCIAS', '1']);
        $response->assertSeeInOrder(['AMARILLAS', '1']);
        $response->assertSeeInOrder(['ROJAS', '0']);
    }

    /**
     * Una portería a cero no lleva minuto: el guard de `GameEvent` lo borra al
     * guardar, y la ficha la cuenta igual.
     */
    public function test_a_goalkeepers_clean_sheets_are_counted(): void
    {
        $keeper = Player::factory()->create([
            'club_id' => $this->club->id,
            'name' => 'Portero Titular',
            'position' => Player::POSITION_GOALKEEPER,
        ]);
        SquadMembership::factory()->create(['team_id' => $this->team->id, 'player_id' => $keeper->id, 'shirt_number' => 1]);

        $this->event(GameEvent::TYPE_CLEAN_SHEET, null, $this->game(), $keeper);

        $this->get(route('site.players.show', $keeper))
            ->assertOk()
            ->assertSeeInOrder(['PORTERÍAS A CERO', '1']);
    }

    /**
     * Los eventos de otra temporada no cuentan en la elegida, pero sí en el
     * total de la carrera.
     */
    public function test_the_season_filter_narrows_the_numbers(): void
    {
        $past = Season::factory()->create(['name' => '2024/25']);
        $pastTeam = Team::factory()->create([
            'club_id' => $this->club->id,
            'season_id' => $past->id,
            'division_id' => Division::factory()->create(['season_id' => $past->id])->id,
        ]);
        // La pertenencia no se crea a mano: inscribir el club en otra temporada
        // ya arrastra su plantilla (observador de `Team::created`, Fase 9), y
        // duplicarla chocaría contra el unique(team_id, player_id).
        SquadMembership::query()
            ->where('team_id', $pastTeam->id)
            ->where('player_id', $this->player->id)
            ->update(['shirt_number' => 7]);

        $this->event(GameEvent::TYPE_GOAL, 30, $this->game());
        $this->event(GameEvent::TYPE_GOAL, 40, $this->game($pastTeam, $past, 2));
        $this->event(GameEvent::TYPE_GOAL, 50, $this->game($pastTeam, $past, 3));

        // Por defecto, la temporada vigente: un gol.
        $this->get(route('site.players.show', $this->player))
            ->assertSee('2026/27')
            ->assertSeeInOrder(['EN 2026/27', 'GOLES', '1'])
            // Y la tabla por temporada lleva el total de la carrera.
            ->assertSeeInOrder(['Por temporada', 'TOTAL']);

        $this->get(route('site.players.show', ['player' => $this->player->id, 'temporada' => $past->id]))
            ->assertSeeInOrder(['EN 2024/25', 'GOLES', '2']);
    }

    public function test_the_season_selector_only_offers_the_seasons_they_played(): void
    {
        Season::factory()->create(['name' => '2019/20']);

        $this->get(route('site.players.show', $this->player))
            ->assertSee('2026/27')
            ->assertDontSee('2019/20');
    }

    /**
     * Con una cesión, el club de la temporada no es el club propietario, y la
     * ficha enseña aquel — es donde jugó.
     */
    public function test_a_loan_shows_the_club_that_fielded_them(): void
    {
        $other = Club::factory()->create(['name' => 'Tapajós SC']);
        $otherTeam = Team::factory()->create([
            'club_id' => $other->id,
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
        ]);

        $loanee = Player::factory()->create(['club_id' => $this->club->id, 'name' => 'Cedido Nunes']);
        SquadMembership::factory()->create([
            'team_id' => $otherTeam->id,
            'player_id' => $loanee->id,
            'shirt_number' => 21,
            'type' => SquadMembership::TYPE_LOAN,
        ]);

        $this->get(route('site.players.show', $loanee))
            ->assertOk()
            ->assertSee('TAPAJÓS SC');
    }

    public function test_the_events_link_to_their_game(): void
    {
        $game = $this->game();
        $this->event(GameEvent::TYPE_GOAL, 12, $game);

        $this->get(route('site.players.show', $this->player))
            ->assertSee(route('site.games.show', $game), escape: false)
            ->assertSee('Gol');
    }

    public function test_a_player_without_events_says_so(): void
    {
        $this->get(route('site.players.show', $this->player))
            ->assertOk()
            ->assertSee('Sin eventos');
    }

    /**
     * Un jugador salido de la liga conserva su ficha: sus goles y tarjetas
     * cuelgan de ella (design D9).
     */
    public function test_a_player_who_left_the_league_keeps_their_page(): void
    {
        $this->player->forceFill(['left_at' => '2026-07-01', 'left_to' => 'Palmeiras'])->save();

        $this->get(route('site.players.show', $this->player))
            ->assertOk()
            ->assertSee('SALIÓ DE LA LIGA')
            ->assertSee('PALMEIRAS');
    }

    public function test_the_squad_and_the_scorers_link_to_the_player(): void
    {
        $this->event(GameEvent::TYPE_GOAL, 10, $this->game());

        $url = route('site.players.show', ['player' => $this->player->id, 'temporada' => $this->season->id]);

        $this->get(route('site.clubs.show', ['club' => $this->club->id, 'tab' => 'jugadores']))
            ->assertSee($url, escape: false);

        $this->get(route('site.scorers'))
            ->assertSee(route('site.players.show', $this->player), escape: false);
    }

    public function test_a_player_that_does_not_exist_is_a_404(): void
    {
        $this->get('/jugadores/999999')->assertNotFound();
    }
}
