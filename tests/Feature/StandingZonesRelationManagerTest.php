<?php

namespace Tests\Feature;

use App\Filament\Resources\Divisions\Pages\EditDivision;
use App\Filament\Resources\Divisions\RelationManagers\StandingZonesRelationManager;
use App\Models\Division;
use App\Models\StandingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class StandingZonesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function manager(Division $division): Testable
    {
        return Livewire::test(StandingZonesRelationManager::class, [
            'ownerRecord' => $division,
            'pageClass' => EditDivision::class,
        ]);
    }

    public function test_relation_manager_renders(): void
    {
        $this->manager(Division::factory()->create())->assertOk();
    }

    public function test_operator_defines_a_zone(): void
    {
        $division = Division::factory()->create();

        $this->manager($division)
            ->mountTableAction('create')
            ->setTableActionData([
                'label' => 'Fase europea',
                'color' => 'blue',
                'from_position' => 1,
                'to_position' => 4,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('standing_zones', [
            'division_id' => $division->id,
            'label' => 'Fase europea',
            'color' => 'blue',
            'from_position' => 1,
            'to_position' => 4,
        ]);
    }

    public function test_an_overlapping_zone_is_rejected_as_a_form_error(): void
    {
        $division = Division::factory()->create();
        StandingZone::factory()->create([
            'division_id' => $division->id,
            'label' => 'Ascenso',
            'from_position' => 1,
            'to_position' => 3,
        ]);

        $this->manager($division)
            ->mountTableAction('create')
            ->setTableActionData([
                'label' => 'Fase europea',
                'color' => 'blue',
                'from_position' => 3,
                'to_position' => 6,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['from_position']);

        $this->assertSame(1, StandingZone::where('division_id', $division->id)->count());
    }

    public function test_an_inverted_range_is_rejected_as_a_form_error(): void
    {
        $division = Division::factory()->create();

        $this->manager($division)
            ->mountTableAction('create')
            ->setTableActionData([
                'label' => 'Descenso',
                'color' => 'red',
                'from_position' => 8,
                'to_position' => 5,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['to_position']);

        $this->assertSame(0, StandingZone::count());
    }

    public function test_a_zone_can_be_widened_without_colliding_with_itself(): void
    {
        $division = Division::factory()->create();
        $zone = StandingZone::factory()->create([
            'division_id' => $division->id,
            'from_position' => 1,
            'to_position' => 2,
        ]);

        $this->manager($division)
            ->mountTableAction('edit', $zone)
            ->setTableActionData([
                'label' => $zone->label,
                'color' => $zone->color,
                'from_position' => 1,
                'to_position' => 4,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(4, $zone->fresh()->to_position);
    }

    public function test_zones_of_another_division_do_not_block_this_one(): void
    {
        $primera = Division::factory()->create();
        $segunda = Division::factory()->create();
        StandingZone::factory()->create(['division_id' => $primera->id, 'from_position' => 1, 'to_position' => 4]);

        $this->manager($segunda)
            ->mountTableAction('create')
            ->setTableActionData([
                'label' => 'Ascenso',
                'color' => 'green',
                'from_position' => 1,
                'to_position' => 4,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, StandingZone::where('division_id', $segunda->id)->count());
    }
}
