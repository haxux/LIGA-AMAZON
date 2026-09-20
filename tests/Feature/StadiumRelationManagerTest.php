<?php

namespace Tests\Feature;

use App\Filament\Resources\Clubs\Pages\EditClub;
use App\Filament\Resources\Clubs\RelationManagers\StadiumRelationManager;
use App\Models\Club;
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
        $club = Club::factory()->create();

        Livewire::test(StadiumRelationManager::class, [
            'ownerRecord' => $club,
            'pageClass' => EditClub::class,
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
            'club_id' => $club->id,
            'name' => 'Arena Teste',
        ]);

        $this->assertTrue($club->fresh()->stadium()->exists());
    }

    public function test_create_action_is_hidden_once_the_club_already_has_a_stadium(): void
    {
        $club = Club::factory()->create();
        $club->stadium()->create([
            'name' => 'Existing Arena',
            'city' => 'Manaus',
            'capacity' => 15000,
        ]);

        Livewire::test(StadiumRelationManager::class, [
            'ownerRecord' => $club,
            'pageClass' => EditClub::class,
        ])
            ->assertTableActionHidden('create');
    }
}
