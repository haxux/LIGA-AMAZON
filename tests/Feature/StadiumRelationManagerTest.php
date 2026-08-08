<?php

namespace Tests\Feature;

use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\RelationManagers\StadiumRelationManager;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StadiumRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_creating_a_stadium_from_the_relation_manager_persists_and_links_it_to_the_team(): void
    {
        $team = Team::factory()->create();

        Livewire::test(StadiumRelationManager::class, [
            'ownerRecord' => $team,
            'pageClass' => EditTeam::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'name' => 'Arena Teste',
                'city' => 'Manaus',
                'capacity' => 20000,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('stadiums', [
            'team_id' => $team->id,
            'name' => 'Arena Teste',
        ]);

        $this->assertTrue($team->fresh()->stadium()->exists());
    }

    public function test_create_action_is_hidden_once_the_team_already_has_a_stadium(): void
    {
        $team = Team::factory()->create();
        $team->stadium()->create([
            'name' => 'Existing Arena',
            'city' => 'Manaus',
            'capacity' => 15000,
        ]);

        Livewire::test(StadiumRelationManager::class, [
            'ownerRecord' => $team,
            'pageClass' => EditTeam::class,
        ])
            ->assertTableActionHidden('create');
    }
}
