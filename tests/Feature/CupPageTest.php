<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Cup;
use App\Models\CupGroup;
use App\Models\CupRound;
use App\Models\CupTeam;
use App\Models\CupTie;
use App\Models\Division;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La copa en la parte pública (Fase 15): su cuadro, sus grupos, y sus partidos
 * donde ya se miran partidos.
 */
class CupPageTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Cup $cup;

    private Team $home;

    private Team $away;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->cup = Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa Amazonas']);

        $division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);
        $this->home = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id, 'name' => 'Manaos FC']);
        $this->away = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id, 'name' => 'Tapajós SC']);

        foreach ([$this->home, $this->away] as $team) {
            CupTeam::create(['cup_id' => $this->cup->id, 'team_id' => $team->id]);
        }
    }

    private function tie(int $legs = 1): CupTie
    {
        $round = CupRound::create([
            'cup_id' => $this->cup->id,
            'name' => $legs === 2 ? 'Semifinal' : 'Final',
            'position' => $legs === 2 ? 1 : 2,
            'legs' => $legs,
        ]);

        return CupTie::create([
            'cup_round_id' => $round->id,
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
        ]);
    }

    private function game(CupTie $tie, Team $home, Team $away, ?int $homeScore, ?int $awayScore): Game
    {
        return Game::create([
            'cup_tie_id' => $tie->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);
    }

    public function test_the_cups_of_a_season_are_listed(): void
    {
        $this->get(route('site.cups.index'))
            ->assertOk()
            ->assertSee('Copa Amazonas')
            ->assertSee('ELIMINATORIA DIRECTA')
            ->assertSee(route('site.cups.show', $this->cup), escape: false);
    }

    public function test_the_section_is_in_the_public_navigation(): void
    {
        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('Copas')
            ->assertSee(route('site.cups.index'), escape: false);
    }

    /**
     * El global de una ida y vuelta no es el marcador de ninguno de los dos
     * partidos: se suma desde el lado de cada equipo.
     */
    public function test_the_bracket_shows_the_aggregate_and_who_went_through(): void
    {
        $tie = $this->tie(legs: 2);
        $this->game($tie, $this->home, $this->away, 1, 0);
        $this->game($tie, $this->away, $this->home, 1, 2);

        $this->get(route('site.cups.show', $this->cup))
            ->assertOk()
            ->assertSee('Semifinal')
            ->assertSee('IDA Y VUELTA')
            ->assertSee('GLOBAL')
            ->assertSeeInOrder(['Manaos FC', '3'])
            ->assertSee('PASA MANAOS FC');
    }

    /**
     * Y cuando pasó por una decisión, se dice por qué: es lo que nadie recuerda
     * seis meses después.
     */
    public function test_a_settled_tie_shows_the_reason(): void
    {
        $tie = $this->tie();
        $this->game($tie, $this->home, $this->away, 1, 1);
        $tie->update(['winner_team_id' => $this->away->id, 'decision_note' => 'Penaltis 5-4']);

        $this->get(route('site.cups.show', $this->cup))
            ->assertSee('PASA TAPAJÓS SC')
            ->assertSee('Penaltis 5-4');
    }

    public function test_a_level_tie_says_it_is_still_open(): void
    {
        $tie = $this->tie();
        $this->game($tie, $this->home, $this->away, 2, 2);

        $this->get(route('site.cups.show', $this->cup))
            ->assertSee('PENDIENTE DE RESOLVER');
    }

    public function test_a_group_stage_shows_its_table(): void
    {
        $cup = Cup::factory()->withGroups()->create(['season_id' => $this->season->id, 'name' => 'Copa con grupos']);
        $group = CupGroup::create(['cup_id' => $cup->id, 'name' => 'A']);

        foreach ([$this->home, $this->away] as $team) {
            CupTeam::create(['cup_id' => $cup->id, 'team_id' => $team->id, 'cup_group_id' => $group->id]);
        }

        Game::create([
            'cup_group_id' => $group->id,
            'group_matchday' => 1,
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_score' => 2,
            'away_score' => 0,
        ]);

        $this->get(route('site.cups.show', $cup))
            ->assertOk()
            ->assertSee('Fase de grupos')
            ->assertSee('Grupo A')
            ->assertSeeInOrder(['Manaos FC', '3']);
    }

    // ── Donde ya se miran partidos ────────────────────────────────────────

    public function test_a_cup_game_reads_as_a_cup_game_in_its_detail(): void
    {
        $tie = $this->tie();
        $game = $this->game($tie, $this->home, $this->away, 3, 1);

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('COPA AMAZONAS')
            ->assertSee('FINAL')
            ->assertSee(route('site.cups.show', $this->cup), escape: false);
    }

    /**
     * Un partido de copa no tiene jornada, así que en el calendario de un club
     * se agrupa por su competición en vez de caer en un cajón de «sin jornada».
     *
     * El calendario arranca en la liga, así que la copa se pide: es el filtro
     * que el propietario quiso, y no un descuido de esta prueba.
     */
    public function test_the_clubs_calendar_groups_a_cup_game_under_its_round(): void
    {
        $tie = $this->tie();
        $this->game($tie, $this->home, $this->away, 3, 1);

        $club = Club::query()->whereKey($this->home->club_id)->first();

        $this->get(route('site.clubs.show', [
            'club' => $club->id,
            'tab' => 'partidos',
            'competicion' => 'copa:'.$this->cup->id,
        ]))
            ->assertOk()
            ->assertSee('Copa Amazonas · Final')
            ->assertDontSee('Sin jornada');
    }

    public function test_a_cup_with_no_rounds_says_so(): void
    {
        $this->get(route('site.cups.show', $this->cup))
            ->assertOk()
            ->assertSee('todavía no tiene rondas');
    }
}
