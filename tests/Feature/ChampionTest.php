<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El campeón matemático (decisión del propietario, 2026-10-21): la tabla lo
 * pinta en el color de la liga y con su corona sólo cuando ya no le puede
 * alcanzar nadie; ir primero no basta.
 */
class ChampionTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Division $division;

    /** @var array<string, Team> */
    private array $teams = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true]);
        $this->division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Primera']);

        foreach (['Lider FC', 'Segundo FC', 'Tercero FC'] as $name) {
            $this->teams[$name] = Team::factory()->create([
                'season_id' => $this->season->id,
                'division_id' => $this->division->id,
                'name' => $name,
            ]);
        }
    }

    private function game(string $home, string $away, ?int $homeScore = null, ?int $awayScore = null, int $number = 1): Game
    {
        $matchday = Matchday::query()->firstOrCreate(
            ['season_id' => $this->season->id, 'division_id' => $this->division->id, 'number' => $number],
            Matchday::factory()->raw(['season_id' => $this->season->id, 'division_id' => $this->division->id, 'number' => $number]),
        );

        return Game::factory()->create([
            'matchday_id' => $matchday->id,
            'home_team_id' => $this->teams[$home]->id,
            'away_team_id' => $this->teams[$away]->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);
    }

    private function champion(): ?Team
    {
        return app(StandingsService::class)->tablesFor($this->season)[0]['champion'];
    }

    public function test_the_leader_is_not_champion_while_someone_can_still_catch_up(): void
    {
        $this->game('Lider FC', 'Segundo FC', 2, 0, 1);
        // Al segundo le queda un partido: 0 + 3 = 3, igual que el líder. Con
        // un empate a puntos decidiría la diferencia de goles: todavía no.
        $this->game('Segundo FC', 'Tercero FC', null, null, 2);

        $this->assertNull($this->champion());
    }

    public function test_the_leader_is_champion_once_nobody_can_reach_them(): void
    {
        $this->game('Lider FC', 'Segundo FC', 2, 0, 1);
        $this->game('Lider FC', 'Tercero FC', 3, 1, 2);
        // Lo que queda sólo da 3 puntos a quien lo gane: nadie llega a 6.
        $this->game('Segundo FC', 'Tercero FC', null, null, 3);

        $this->assertTrue($this->champion()?->is($this->teams['Lider FC']));

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('aria-label="Campeón"', false);
    }

    /**
     * El título no se guarda: se calcula en cada visita. Si se añaden
     * jornadas y alguien vuelve a poder alcanzarle, la corona se va; y vuelve
     * en cuanto deja de poder.
     */
    public function test_adding_matchdays_takes_the_title_away_until_it_is_clinched_again(): void
    {
        $this->game('Lider FC', 'Segundo FC', 2, 0, 1);
        $this->game('Lider FC', 'Tercero FC', 3, 1, 2);
        $this->assertTrue($this->champion()?->is($this->teams['Lider FC']));

        // Se añaden dos jornadas más: el Segundo, con 0 puntos, podría sumar 6
        // y empatar al líder. Deja de ser campeón.
        $this->game('Segundo FC', 'Tercero FC', null, null, 3);
        $pending = $this->game('Segundo FC', 'Lider FC', null, null, 4);
        $this->assertNull($this->champion());

        // El líder gana ese partido: ya nadie llega. Vuelve la corona.
        $pending->update(['home_score' => 0, 'away_score' => 1]);
        $this->assertTrue($this->champion()?->is($this->teams['Lider FC']));
    }

    public function test_with_the_calendar_finished_the_first_is_champion_even_level_on_points(): void
    {
        $this->game('Lider FC', 'Segundo FC', 3, 0, 1);
        $this->game('Segundo FC', 'Tercero FC', 1, 0, 2);
        $this->game('Tercero FC', 'Lider FC', 1, 0, 3);

        // Los tres a 3 puntos: manda la diferencia de goles, y ya no cambia.
        $this->assertTrue($this->champion()?->is($this->teams['Lider FC']));
    }

    public function test_nothing_played_means_no_champion(): void
    {
        $this->game('Lider FC', 'Segundo FC', null, null, 1);

        $this->assertNull($this->champion());

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertDontSee('aria-label="Campeón"', false);
    }
}
