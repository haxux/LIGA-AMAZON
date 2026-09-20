<?php

namespace Tests\Feature;

use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Models\Club;
use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListTeams::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreateTeam::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        $team = Team::factory()->create();

        Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])->assertOk();
    }

    /**
     * Crear un equipo es inscribir un club en una temporada: el nombre, el
     * escudo y el año de fundación viven en el club desde la Fase 9.
     */
    public function test_can_enrol_a_club_in_a_season_via_the_form(): void
    {
        $season = Season::factory()->create();
        $club = Club::factory()->create(['name' => 'Rio Branco EC']);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $season->id,
                'club_id' => $club->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', [
            'season_id' => $season->id,
            'club_id' => $club->id,
        ]);
    }

    public function test_can_move_a_team_to_another_club_via_the_form(): void
    {
        $team = Team::factory()->create();
        $other = Club::factory()->create(['name' => 'Rio Branco EC']);

        Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])
            ->fillForm([
                'club_id' => $other->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'club_id' => $other->id,
        ]);
        $this->assertSame('Rio Branco EC', $team->fresh()->name);
    }

    public function test_enrolling_one_club_twice_in_a_season_is_rejected_as_a_form_error(): void
    {
        $season = Season::factory()->create();
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        Team::factory()->create(['season_id' => $season->id, 'club_id' => $club->id]);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $season->id,
                'club_id' => $club->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['club_id']);

        $this->assertSame(1, Team::where('season_id', $season->id)->where('club_id', $club->id)->count());
    }

    public function test_one_club_can_play_in_several_seasons(): void
    {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        Team::factory()->create(['season_id' => $seasonA->id, 'club_id' => $club->id]);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $seasonB->id,
                'club_id' => $club->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Team::where('club_id', $club->id)->count());
    }

    public function test_a_team_reads_its_identity_from_its_club(): void
    {
        $club = Club::factory()->create(['name' => 'Belém Athletic', 'short_name' => 'BEL', 'founded_year' => 2001]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $this->assertSame('Belém Athletic', $team->name);
        $this->assertSame('BEL', $team->short_name);
        $this->assertSame(2001, $team->founded_year);
    }

    public function test_deleting_a_team_referenced_by_a_game_shows_a_danger_notification_and_keeps_the_team(): void
    {
        $season = Season::factory()->create();
        $homeTeam = Team::factory()->create(['season_id' => $season->id]);
        $awayTeam = Team::factory()->create(['season_id' => $season->id]);
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        Game::create([
            'matchday_id' => $matchday->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        Livewire::test(EditTeam::class, ['record' => $homeTeam->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('Team cannot be deleted');

        $this->assertTrue(Team::query()->whereKey($homeTeam->id)->exists());
    }

    public function test_deleting_a_team_referenced_by_a_game_via_the_table_row_action_shows_a_danger_notification(): void
    {
        $season = Season::factory()->create();
        $homeTeam = Team::factory()->create(['season_id' => $season->id]);
        $awayTeam = Team::factory()->create(['season_id' => $season->id]);
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        Game::create([
            'matchday_id' => $matchday->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        Livewire::test(ListTeams::class)
            ->callTableAction('delete', $homeTeam)
            ->assertNotified('Team cannot be deleted');

        $this->assertTrue(Team::query()->whereKey($homeTeam->id)->exists());
    }

    public function test_can_assign_a_team_to_a_division(): void
    {
        $season = Season::factory()->create();
        $division = Division::factory()->create(['season_id' => $season->id]);
        $team = Team::factory()->create(['season_id' => $season->id, 'division_id' => null]);

        Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])
            ->fillForm([
                'division_id' => $division->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'division_id' => $division->id,
        ]);
    }

    public function test_team_persists_with_a_null_division(): void
    {
        $season = Season::factory()->create();
        $club = Club::factory()->create(['name' => 'Sin División FC']);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $season->id,
                'club_id' => $club->id,
                'division_id' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', [
            'club_id' => $club->id,
            'division_id' => null,
        ]);
    }

    public function test_division_select_only_offers_divisions_from_the_selected_season(): void
    {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();
        $divisionFromOtherSeason = Division::factory()->create(['season_id' => $seasonB->id, 'name' => 'Primera']);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $seasonA->id,
                'name' => 'Foreign Division FC',
                'short_name' => 'FDF',
                'founded_year' => 2000,
                'division_id' => $divisionFromOtherSeason->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['division_id']);

        $this->assertDatabaseMissing('teams', [
            'name' => 'Foreign Division FC',
        ]);
    }
}
