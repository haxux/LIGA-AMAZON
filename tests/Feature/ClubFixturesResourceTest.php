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
        $this->season = Season::factory()->create(['is_current' => true]);
        $this->actingAs(User::factory()->coachOf($this->club)->create());
        Filament::setCurrentPanel('club');
    }

    private function gameFor(?Club $club, ?Season $season = null, bool $atHome = true): Game
    {
        $season ??= $this->season;
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        $mine = Team::factory()->create(['season_id' => $season->id, 'club_id' => ($club ?? Club::factory()->create())->id, 'division_id' => $matchday->division_id]);
        $rival = Team::factory()->create(['season_id' => $season->id, 'division_id' => $matchday->division_id]);

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $atHome ? $mine->id : $rival->id,
            'away_team_id' => $atHome ? $rival->id : $mine->id,
        ]);
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

    public function test_the_coach_cannot_create_games(): void
    {
        $this->assertFalse(FixtureResource::canCreate());
    }
}
