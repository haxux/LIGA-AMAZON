<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Fixtures\FixtureResource;
use App\Filament\Club\Resources\Fixtures\Pages\ListFixtures;
use App\Models\Club;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mis Enfrentamientos (Fase 10): el calendario del club del técnico.
 */
class ClubFixturesResourceTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->actingAs(User::factory()->coachOf($this->club)->create());
        Filament::setCurrentPanel('club');
    }

    private function gameFor(?Club $club, ?Season $season = null, bool $atHome = true): Game
    {
        $season ??= $this->season;
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        // Un club se inscribe UNA vez por temporada, así que dos jornadas de la
        // misma temporada reutilizan su equipo en lugar de chocar contra el
        // unique(season_id, club_id).
        $mine = $this->teamFor($club ?? Club::factory()->create(), $season, $matchday->division_id);
        $rival = Team::factory()->create(['season_id' => $season->id, 'division_id' => $matchday->division_id]);

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $atHome ? $mine->id : $rival->id,
            'away_team_id' => $atHome ? $rival->id : $mine->id,
        ]);
    }

    private function teamFor(Club $club, Season $season, int $divisionId): Team
    {
        return Team::query()->where('season_id', $season->id)->where('club_id', $club->id)->first()
            ?? Team::factory()->create(['season_id' => $season->id, 'club_id' => $club->id, 'division_id' => $divisionId]);
    }

    public function test_lists_only_games_the_club_plays(): void
    {
        $mine = $this->gameFor($this->club);
        $theirs = $this->gameFor(null);

        Livewire::test(ListFixtures::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_away_games_count_as_mine_too(): void
    {
        $away = $this->gameFor($this->club, atHome: false);

        Livewire::test(ListFixtures::class)->assertCanSeeTableRecords([$away]);
    }

    /**
     * El club sobrevive a las temporadas, así que sus partidos de años
     * anteriores siguen siendo suyos aunque el equipo de entonces sea otra fila.
     */
    public function test_games_from_a_previous_season_still_belong_to_the_club(): void
    {
        $old = Season::factory()->create(['name' => '2024/25']);
        $previous = $this->gameFor($this->club, $old);

        Livewire::test(ListFixtures::class)->assertCanSeeTableRecords([$previous]);
    }

    /**
     * La jornada se filtra por NÚMERO y no por fila: cada división lleva su
     * propio calendario, así que hay tantas "jornada 1" como divisiones y
     * temporadas, y un desplegable de filas las mostraría repetidas y sin
     * forma de distinguirlas.
     */
    public function test_the_matchday_filter_keeps_only_that_matchday(): void
    {
        $first = $this->gameFor($this->club);
        $second = $this->gameFor($this->club);

        Livewire::test(ListFixtures::class)
            ->filterTable('jornada', $first->matchday->number)
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second]);
    }

    public function test_the_matchday_filter_only_offers_the_clubs_own_numbers(): void
    {
        $mine = $this->gameFor($this->club);
        $theirs = $this->gameFor(null);

        $options = Livewire::test(ListFixtures::class)
            ->instance()
            ->getTable()
            ->getFilter('jornada')
            ->getOptions();

        $this->assertSame([$mine->matchday->number => $mine->matchday->number], $options);
        $this->assertArrayNotHasKey($theirs->matchday->number, $options);
    }

    public function test_the_season_filter_keeps_only_that_season(): void
    {
        $current = $this->gameFor($this->club);
        $old = $this->gameFor($this->club, Season::factory()->create(['name' => '2023/24']));

        Livewire::test(ListFixtures::class)
            ->filterTable('temporada', $this->season->id)
            ->assertCanSeeTableRecords([$current])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_the_coach_cannot_create_games(): void
    {
        $this->assertFalse(FixtureResource::canCreate());
    }
}
