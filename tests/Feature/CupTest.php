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
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\CupService;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Las copas (Fase 15): rondas en vez de jornadas, cuadro en vez de tabla, y
 * equipos de divisiones distintas cruzándose entre ellos.
 */
class CupTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Cup $cup;

    private CupService $cups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->cup = Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa Amazonas']);
        $this->cups = app(CupService::class);
    }

    private function team(string $name, ?Division $division = null): Team
    {
        return Team::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $division?->id,
            'name' => $name,
        ]);
    }

    private function round(string $name = 'Final', int $position = 1, int $legs = 1): CupRound
    {
        return CupRound::create([
            'cup_id' => $this->cup->id,
            'name' => $name,
            'position' => $position,
            'legs' => $legs,
        ]);
    }

    private function tie(CupRound $round, Team $home, Team $away): CupTie
    {
        return CupTie::create([
            'cup_round_id' => $round->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
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

    // ── El modelo ─────────────────────────────────────────────────────────

    /**
     * Es lo que una división no sabe hacer, y por lo que la copa existe.
     */
    public function test_a_cup_crosses_teams_from_different_divisions(): void
    {
        $primera = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);
        $segunda = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Segunda']);

        $one = $this->team('Manaos FC', $primera);
        $two = $this->team('Tapajós SC', $segunda);

        CupTeam::create(['cup_id' => $this->cup->id, 'team_id' => $one->id]);
        CupTeam::create(['cup_id' => $this->cup->id, 'team_id' => $two->id]);

        $this->assertSame(2, $this->cup->participants()->count());
        $this->assertEqualsCanonicalizing(
            [$primera->id, $segunda->id],
            $this->cup->teams()->pluck('division_id')->all(),
        );
    }

    public function test_a_game_belongs_to_exactly_one_competition(): void
    {
        $round = $this->round();
        $tie = $this->tie($round, $this->team('Uno'), $this->team('Dos'));
        $matchday = Matchday::factory()->create(['season_id' => $this->season->id]);

        // Ni a ninguna...
        try {
            Game::create(['home_team_id' => $tie->home_team_id, 'away_team_id' => $tie->away_team_id]);
            $this->fail('un partido sin competición no debería guardarse');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('jornada', $exception->validator->errors()->first());
        }

        // ...ni a dos.
        $this->expectException(ValidationException::class);
        Game::create([
            'matchday_id' => $matchday->id,
            'cup_tie_id' => $tie->id,
            'home_team_id' => $tie->home_team_id,
            'away_team_id' => $tie->away_team_id,
        ]);
    }

    public function test_a_round_is_played_over_one_or_two_legs(): void
    {
        $this->expectException(ValidationException::class);

        $this->round(legs: 3);
    }

    public function test_only_one_of_the_two_teams_can_go_through(): void
    {
        $round = $this->round();
        $tie = $this->tie($round, $this->team('Uno'), $this->team('Dos'));
        $stranger = $this->team('Tres');

        $this->expectException(ValidationException::class);

        $tie->update(['winner_team_id' => $stranger->id]);
    }

    // ── El global de una eliminatoria ─────────────────────────────────────

    public function test_a_single_game_decides_who_goes_through(): void
    {
        $round = $this->round();
        $home = $this->team('Manaos FC');
        $away = $this->team('Tapajós SC');
        $tie = $this->tie($round, $home, $away);

        $this->game($tie, $home, $away, 2, 1);

        $result = $this->cups->result($tie->fresh());

        $this->assertTrue($result->isFinished());
        $this->assertSame(2, $result->homeGoals);
        $this->assertTrue($result->winner->is($home));
        $this->assertFalse($result->needsDecision());
    }

    /**
     * En una ida y vuelta el global no es el marcador de ninguno de los dos
     * partidos: se suma desde el lado de cada equipo, y el visitante de la ida
     * es el local de la vuelta.
     */
    public function test_two_legs_add_up_from_each_teams_side(): void
    {
        $round = $this->round('Semifinal', legs: 2);
        $home = $this->team('Manaos FC');
        $away = $this->team('Tapajós SC');
        $tie = $this->tie($round, $home, $away);

        $this->game($tie, $home, $away, 1, 0);
        $this->game($tie, $away, $home, 1, 2);

        $result = $this->cups->result($tie->fresh());

        $this->assertSame(3, $result->homeGoals);
        $this->assertSame(1, $result->awayGoals);
        $this->assertTrue($result->winner->is($home));
    }

    public function test_a_tie_is_not_finished_until_every_leg_is_played(): void
    {
        $round = $this->round('Semifinal', legs: 2);
        $home = $this->team('Manaos FC');
        $away = $this->team('Tapajós SC');
        $tie = $this->tie($round, $home, $away);

        $this->game($tie, $home, $away, 3, 0);
        $this->game($tie, $away, $home, null, null);

        $result = $this->cups->result($tie->fresh());

        $this->assertFalse($result->isFinished());
        $this->assertNull($result->winner, 'nadie pasa con la vuelta sin jugar');
    }

    /**
     * Empatada y jugada entera, la aplicación se para: no inventa un ganador
     * por goles fuera ni por penaltis que nadie ha registrado.
     */
    public function test_a_drawn_tie_waits_for_the_administrator(): void
    {
        $round = $this->round();
        $home = $this->team('Manaos FC');
        $away = $this->team('Tapajós SC');
        $tie = $this->tie($round, $home, $away);

        $this->game($tie, $home, $away, 1, 1);

        $result = $this->cups->result($tie->fresh());

        $this->assertTrue($result->needsDecision());
        $this->assertNull($result->winner);
    }

    public function test_the_administrator_says_who_goes_through_and_why(): void
    {
        $round = $this->round();
        $home = $this->team('Manaos FC');
        $away = $this->team('Tapajós SC');
        $tie = $this->tie($round, $home, $away);
        $this->game($tie, $home, $away, 1, 1);

        $this->cups->decide($tie, User::factory()->create(), $away, 'Penaltis 4-2');

        $result = $this->cups->result($tie->fresh());

        $this->assertTrue($result->winner->is($away));
        $this->assertSame('Penaltis 4-2', $result->note);
        $this->assertFalse($result->needsDecision());
    }

    public function test_a_decision_needs_a_reason_and_an_administrator(): void
    {
        $round = $this->round();
        $home = $this->team('Manaos FC');
        $away = $this->team('Tapajós SC');
        $tie = $this->tie($round, $home, $away);
        $this->game($tie, $home, $away, 0, 0);

        $coach = User::factory()->coachOf(Club::factory()->create())->create();

        try {
            $this->cups->decide($tie, $coach, $home, 'Porque sí');
            $this->fail('un técnico no resuelve una eliminatoria');
        } catch (ValidationException) {
            $this->assertNull($tie->fresh()->winner_team_id);
        }

        $this->expectException(ValidationException::class);
        $this->cups->decide($tie, User::factory()->create(), $home, '');
    }

    // ── La fase de grupos ─────────────────────────────────────────────────

    /**
     * Un grupo es una liga pequeña, y se clasifica con el mismo código que una
     * división: duplicar el cómputo sería garantizar que los dos se separen.
     */
    public function test_a_group_has_its_own_table(): void
    {
        $cup = Cup::factory()->withGroups()->create(['season_id' => $this->season->id, 'name' => 'Copa con grupos']);
        $group = CupGroup::create(['cup_id' => $cup->id, 'name' => 'A']);

        $one = $this->team('Manaos FC');
        $two = $this->team('Tapajós SC');
        $three = $this->team('Iquitos United');

        foreach ([$one, $two, $three] as $team) {
            CupTeam::create(['cup_id' => $cup->id, 'team_id' => $team->id, 'cup_group_id' => $group->id]);
        }

        Game::create(['cup_group_id' => $group->id, 'group_matchday' => 1, 'home_team_id' => $one->id, 'away_team_id' => $two->id, 'home_score' => 3, 'away_score' => 0]);
        Game::create(['cup_group_id' => $group->id, 'group_matchday' => 2, 'home_team_id' => $two->id, 'away_team_id' => $three->id, 'home_score' => 1, 'away_score' => 1]);

        $table = $this->cups->groupTable($group->fresh());

        $this->assertCount(3, $table);
        $this->assertTrue($table->first()->team->is($one));
        $this->assertSame(3, $table->first()->points);
        $this->assertSame(1, $table->last()->points, 'el que perdió 3-0 y no jugó más va último');
    }

    /**
     * Y los partidos de copa no cuentan para la tabla de ninguna división: son
     * de otra competición.
     */
    public function test_cup_games_stay_out_of_a_divisions_table(): void
    {
        $division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);
        $one = $this->team('Manaos FC', $division);
        $two = $this->team('Tapajós SC', $division);

        $round = $this->round();
        $tie = $this->tie($round, $one, $two);
        $this->game($tie, $one, $two, 5, 0);

        $table = app(StandingsService::class)->forDivision($division);

        $this->assertSame(0, $table->first()->played, 'la goleada de copa no toca la liga');
    }
}
