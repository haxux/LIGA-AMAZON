<?php

namespace Tests\Feature;

use App\Filament\Resources\Divisions\Pages\CreateDivision;
use App\Filament\Resources\Divisions\Pages\EditDivision;
use App\Filament\Resources\Divisions\Pages\ListDivisions;
use App\Models\Division;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DivisionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListDivisions::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreateDivision::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        $division = Division::factory()->create();

        Livewire::test(EditDivision::class, ['record' => $division->getRouteKey()])->assertOk();
    }

    public function test_can_create_a_division_via_the_form(): void
    {
        $season = Season::factory()->create();

        Livewire::test(CreateDivision::class)
            ->fillForm([
                'season_id' => $season->id,
                'name' => 'Primera',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('divisions', [
            'season_id' => $season->id,
            'name' => 'Primera',
        ]);
    }

    public function test_duplicate_division_name_within_same_season_is_rejected_as_a_form_error(): void
    {
        $season = Season::factory()->create();
        Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);

        Livewire::test(CreateDivision::class)
            ->fillForm([
                'season_id' => $season->id,
                'name' => 'Primera',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(1, Division::where('season_id', $season->id)->where('name', 'Primera')->count());
    }

    public function test_deleting_a_division_with_teams_shows_a_danger_notification_and_keeps_the_division(): void
    {
        $season = Season::factory()->create();
        $division = Division::factory()->create(['season_id' => $season->id]);
        Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id]);

        Livewire::test(EditDivision::class, ['record' => $division->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('Division cannot be deleted');

        $this->assertTrue(Division::query()->whereKey($division->id)->exists());
    }
}
