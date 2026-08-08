<?php

namespace Tests\Feature;

use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_can_create_a_team_via_the_form(): void
    {
        $season = Season::factory()->create();

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $season->id,
                'name' => 'Rio Branco EC',
                'short_name' => 'RBR',
                'founded_year' => 1998,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', [
            'season_id' => $season->id,
            'name' => 'Rio Branco EC',
        ]);
    }

    public function test_can_edit_a_team_via_the_form(): void
    {
        $team = Team::factory()->create(['short_name' => 'OLD']);

        Livewire::test(EditTeam::class, ['record' => $team->getRouteKey()])
            ->fillForm([
                'short_name' => 'NEW',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'short_name' => 'NEW',
        ]);
    }

    public function test_duplicate_team_name_within_same_season_is_rejected_as_a_form_error(): void
    {
        $season = Season::factory()->create();
        Team::factory()->create(['season_id' => $season->id, 'name' => 'Manaos FC']);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $season->id,
                'name' => 'Manaos FC',
                'short_name' => 'MA2',
                'founded_year' => 2000,
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(1, Team::where('season_id', $season->id)->where('name', 'Manaos FC')->count());
    }

    public function test_duplicate_team_name_across_different_seasons_is_allowed(): void
    {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();
        Team::factory()->create(['season_id' => $seasonA->id, 'name' => 'Manaos FC']);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $seasonB->id,
                'name' => 'Manaos FC',
                'short_name' => 'MAN',
                'founded_year' => 2000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Team::where('season_id', $seasonB->id)->where('name', 'Manaos FC')->count());
    }

    public function test_uploading_a_crest_stores_it_under_crests_on_the_public_disk(): void
    {
        Storage::fake('public');
        $season = Season::factory()->create();
        $file = UploadedFile::fake()->image('crest.png');

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $season->id,
                'name' => 'Belém Athletic',
                'short_name' => 'BEL',
                'founded_year' => 2001,
                'crest_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $team = Team::where('name', 'Belém Athletic')->firstOrFail();

        $this->assertStringStartsWith('crests/', $team->crest_path);
        Storage::disk('public')->assertExists($team->crest_path);
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

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $season->id,
                'name' => 'Sin División FC',
                'short_name' => 'SDF',
                'founded_year' => 2000,
                'division_id' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', [
            'name' => 'Sin División FC',
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
