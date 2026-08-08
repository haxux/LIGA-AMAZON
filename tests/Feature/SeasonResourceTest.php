<?php

namespace Tests\Feature;

use App\Filament\Resources\Seasons\Pages\CreateSeason;
use App\Filament\Resources\Seasons\Pages\EditSeason;
use App\Filament\Resources\Seasons\Pages\ListSeasons;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListSeasons::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreateSeason::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        $season = Season::factory()->create();

        Livewire::test(EditSeason::class, ['record' => $season->getRouteKey()])->assertOk();
    }

    public function test_can_create_a_season_via_the_form(): void
    {
        Livewire::test(CreateSeason::class)
            ->fillForm([
                'name' => '2026/27',
                'start_date' => '2026-08-01',
                'end_date' => '2027-05-31',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('seasons', [
            'name' => '2026/27',
        ]);
    }

    public function test_duplicate_season_name_is_rejected_as_a_form_error(): void
    {
        Season::factory()->create(['name' => '2025/26']);

        Livewire::test(CreateSeason::class)
            ->fillForm([
                'name' => '2025/26',
                'start_date' => '2026-08-01',
                'end_date' => '2027-05-31',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(1, Season::where('name', '2025/26')->count());
    }

    public function test_can_edit_a_season_via_the_form(): void
    {
        $season = Season::factory()->create(['name' => '2025/26']);

        Livewire::test(EditSeason::class, ['record' => $season->getRouteKey()])
            ->fillForm([
                'name' => '2025/26 - Renamed',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'name' => '2025/26 - Renamed',
        ]);
    }

    public function test_toggling_is_current_unsets_the_previous_current_season_without_a_form_error(): void
    {
        $current = Season::factory()->create(['is_current' => true]);
        $other = Season::factory()->create(['is_current' => false]);

        Livewire::test(EditSeason::class, ['record' => $other->getRouteKey()])
            ->fillForm([
                'is_current' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($current->fresh()->is_current);
        $this->assertTrue($other->fresh()->is_current);
    }

    public function test_is_current_column_renders_in_the_list(): void
    {
        Season::factory()->create(['is_current' => true]);
        Season::factory()->create(['is_current' => false]);

        Livewire::test(ListSeasons::class)
            ->assertOk()
            ->assertTableColumnExists('is_current')
            ->sortTable('is_current')
            ->assertOk();
    }
}
