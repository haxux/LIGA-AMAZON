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
use App\Models\StandingZone;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
            ->assertSeeInOrder(['3', 'GLOBAL'])
            // La ida y la vuelta, al lado del global y leídas desde el local
            // del cruce: la vuelta 1-2 se lee 2 – 1.
            ->assertSeeInOrder(['IDA', '1 – 0', 'VUELTA', '2 – 1'])
            // Quién pasó no se escribe: su nombre brilla.
            ->assertDontSee('PASA')
            ->assertSeeInOrder(['tie-winner', 'Manaos FC'], false);
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
            ->assertSeeInOrder(['tie-winner', 'Tapajós SC'], false)
            ->assertSee('Penaltis 5-4');
    }

    /**
     * Las fases preliminares se juegan antes de los grupos, y así se enseñan:
     * arriba, separadas del cuadro.
     */
    public function test_preliminary_rounds_come_before_the_bracket(): void
    {
        CupRound::create(['cup_id' => $this->cup->id, 'name' => 'Primera fase', 'stage' => CupRound::STAGE_PRELIMINARY, 'position' => 1, 'legs' => 2]);
        CupRound::create(['cup_id' => $this->cup->id, 'name' => 'Final', 'position' => 2, 'legs' => 1]);

        $this->get(route('site.cups.show', $this->cup))
            ->assertOk()
            ->assertSeeInOrder(['Fases preliminares', 'Primera fase', 'Cuadro', 'Final']);
    }

    public function test_without_preliminary_rounds_there_is_no_such_section(): void
    {
        $this->tie();

        $this->get(route('site.cups.show', $this->cup))->assertDontSee('Fases preliminares');
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

    public function test_group_zones_band_the_tables_and_show_a_legend(): void
    {
        $cup = Cup::factory()->withGroups()->create(['season_id' => $this->season->id, 'name' => 'Copa con grupos']);
        $group = CupGroup::create(['cup_id' => $cup->id, 'name' => 'A']);
        CupTeam::create(['cup_id' => $cup->id, 'team_id' => $this->home->id, 'cup_group_id' => $group->id]);
        StandingZone::factory()->create([
            'division_id' => null, 'cup_id' => $cup->id, 'label' => 'Pasa a octavos', 'color' => 'green',
            'from_position' => 1, 'to_position' => 2,
        ]);

        $this->get(route('site.cups.show', $cup))
            ->assertOk()
            ->assertSee('PASA A OCTAVOS')
            ->assertSee('background-color: #2FA36B', false);
    }

    public function test_a_cup_zone_does_not_clash_with_a_division_zone_on_the_same_positions(): void
    {
        $cup = Cup::factory()->withGroups()->create(['season_id' => $this->season->id, 'name' => 'Otra copa']);
        StandingZone::factory()->create(['division_id' => null, 'cup_id' => $cup->id, 'label' => 'Pasa', 'from_position' => 1, 'to_position' => 2]);
        StandingZone::factory()->create(['label' => 'Ascenso', 'from_position' => 1, 'to_position' => 2]);

        $this->assertSame(2, StandingZone::count());

        $this->expectException(ValidationException::class);

        StandingZone::factory()->create(['division_id' => null, 'cup_id' => $cup->id, 'label' => 'Otra', 'from_position' => 2, 'to_position' => 3]);
    }

    /**
     * Tres grupos de tres equipos, para que haya mejores terceros.
     */
    private function cupWithThirds(): Cup
    {
        $cup = Cup::factory()->withGroups()->create(['season_id' => $this->season->id, 'name' => 'Copa con terceros']);
        $division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Segunda']);

        foreach (['A', 'B', 'C'] as $name) {
            $group = CupGroup::create(['cup_id' => $cup->id, 'name' => $name]);

            foreach (range(1, 3) as $n) {
                $team = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id, 'name' => "{$name}{$n} FC"]);
                CupTeam::create(['cup_id' => $cup->id, 'team_id' => $team->id, 'cup_group_id' => $group->id]);
            }
        }

        return $cup;
    }

    public function test_best_thirds_zones_band_that_table_and_not_the_groups(): void
    {
        $cup = $this->cupWithThirds();
        StandingZone::factory()->create([
            'division_id' => null, 'cup_id' => $cup->id, 'stage' => StandingZone::STAGE_THIRDS,
            'label' => 'Pasa como mejor tercero', 'color' => 'amber', 'from_position' => 1, 'to_position' => 2,
        ]);

        $this->assertSame(1, $cup->thirdsStandingZones()->count());
        $this->assertSame(0, $cup->standingZones()->count(), 'La zona de terceros no es de los grupos.');

        $this->get(route('site.cups.show', $cup))
            ->assertOk()
            ->assertSee('Mejores terceros')
            ->assertSee('PASA COMO MEJOR TERCERO')
            ->assertSee('background-color: #FFB800', false);
    }

    public function test_group_and_thirds_zones_count_positions_independently(): void
    {
        $cup = $this->cupWithThirds();

        StandingZone::factory()->create(['division_id' => null, 'cup_id' => $cup->id, 'label' => 'Pasa', 'from_position' => 1, 'to_position' => 2]);
        StandingZone::factory()->create(['division_id' => null, 'cup_id' => $cup->id, 'stage' => StandingZone::STAGE_THIRDS, 'label' => 'Pasa', 'from_position' => 1, 'to_position' => 2]);

        $this->assertSame(2, StandingZone::where('cup_id', $cup->id)->count());

        $this->expectException(ValidationException::class);

        StandingZone::factory()->create(['division_id' => null, 'cup_id' => $cup->id, 'stage' => StandingZone::STAGE_THIRDS, 'label' => 'Otra', 'from_position' => 2, 'to_position' => 3]);
    }

    public function test_a_cup_with_fewer_than_three_groups_has_no_thirds_zones_to_define(): void
    {
        $cup = Cup::factory()->withGroups()->create(['season_id' => $this->season->id, 'name' => 'Dos grupos']);
        CupGroup::create(['cup_id' => $cup->id, 'name' => 'A']);
        CupGroup::create(['cup_id' => $cup->id, 'name' => 'B']);

        $this->assertFalse($cup->hasBestThirds());
        $this->assertTrue($this->cupWithThirds()->hasBestThirds());
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
