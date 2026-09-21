<?php

namespace Tests\Feature;

use App\Filament\Resources\Clubs\Pages\EditClub;
use App\Filament\Resources\Clubs\RelationManagers\PlayersRelationManager;
use App\Models\Club;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Desde la Fase 9 los jugadores cuelgan del club, no del equipo de una
 * temporada: este gestor edita su identidad y vive en `ClubResource`.
 */
class PlayersRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lists_only_the_owning_clubs_players(): void
    {
        $clubA = Club::factory()->create();
        $clubB = Club::factory()->create();
        $playerA = Player::factory()->create(['club_id' => $clubA->id]);
        $playerB = Player::factory()->create(['club_id' => $clubB->id]);

        Livewire::test(PlayersRelationManager::class, [
            'ownerRecord' => $clubA,
            'pageClass' => EditClub::class,
        ])
            ->assertCanSeeTableRecords([$playerA])
            ->assertCanNotSeeTableRecords([$playerB]);
    }

    public function test_creating_a_player_from_the_relation_manager_files_them_under_the_club(): void
    {
        $club = Club::factory()->create();

        Livewire::test(PlayersRelationManager::class, [
            'ownerRecord' => $club,
            'pageClass' => EditClub::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'name' => 'Squad Player',
                'position' => 'Defender',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('players', [
            'club_id' => $club->id,
            'name' => 'Squad Player',
        ]);
    }

    /**
     * La posición específica viaja con la identidad, así que se pone también
     * aquí y no sólo desde el panel del técnico (Fase 10).
     */
    public function test_the_administrator_files_a_specific_position_from_the_club(): void
    {
        $club = Club::factory()->create();

        Livewire::test(PlayersRelationManager::class, [
            'ownerRecord' => $club,
            'pageClass' => EditClub::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'name' => 'Left Back',
                'position' => 'Defender',
                'specific_position' => 'LI',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('players', [
            'club_id' => $club->id,
            'name' => 'Left Back',
            'specific_position' => 'LI',
        ]);
    }

    /**
     * El dorsal pertenece a la plantilla de una temporada, así que no se pide
     * al dar de alta la identidad de un jugador.
     */
    public function test_the_identity_form_does_not_ask_for_a_shirt_number(): void
    {
        $component = Livewire::test(PlayersRelationManager::class, [
            'ownerRecord' => Club::factory()->create(),
            'pageClass' => EditClub::class,
        ])->mountTableAction('create')->instance();

        $this->assertFalse(str_contains(json_encode($component->mountedActions ?? []), 'shirt_number'));
    }
}
