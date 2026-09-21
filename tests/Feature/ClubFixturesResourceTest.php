<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El calendario de un club se lee en su ficha pública, así que el panel del
 * técnico ya no lo repite: una segunda pantalla para lo mismo sólo añade
 * sitios donde mirar.
 */
class ClubFixturesResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_coach_panel_no_longer_serves_fixtures(): void
    {
        $club = Club::factory()->create();
        $coach = User::factory()->coachOf($club)->create();

        $this->actingAs($coach, 'club');

        $this->get('/club/enfrentamientos')->assertNotFound();
    }

    /**
     * Y donde sí está su calendario es en la ficha pública, que además lo
     * enseña sin necesidad de sesión.
     */
    public function test_the_public_profile_holds_the_clubs_calendar(): void
    {
        $season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        $team = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id, 'division_id' => $division->id]);
        $rival = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'name' => 'Tapajós SC']);
        $matchday = Matchday::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'number' => 1]);

        Game::factory()->for($matchday)->create([
            'home_team_id' => $team->id,
            'away_team_id' => $rival->id,
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $this->get(route('site.clubs.show', ['club' => $club->id, 'tab' => 'partidos']))
            ->assertOk()
            ->assertSee('Jornada 1')
            ->assertSee('Tapajós SC');
    }
}
