<?php

namespace Tests\Feature;

use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\RelationManagers\PlayersRelationManager;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlayersRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lists_only_the_owning_teams_players(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $playerA = Player::factory()->create(['team_id' => $teamA->id]);
        $playerB = Player::factory()->create(['team_id' => $teamB->id]);

        Livewire::test(PlayersRelationManager::class, [
            'ownerRecord' => $teamA,
            'pageClass' => EditTeam::class,
        ])
            ->assertCanSeeTableRecords([$playerA])
            ->assertCanNotSeeTableRecords([$playerB]);
    }

    public function test_creating_a_player_from_the_relation_manager_adds_it_directly_to_the_team(): void
    {
        $team = Team::factory()->create();

        Livewire::test(PlayersRelationManager::class, [
            'ownerRecord' => $team,
            'pageClass' => EditTeam::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'name' => 'Squad Player',
                'position' => 'Defender',
                'shirt_number' => 4,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('players', [
            'team_id' => $team->id,
            'name' => 'Squad Player',
        ]);
    }

    public function test_duplicate_shirt_number_within_the_relation_managers_team_is_rejected_as_a_form_error(): void
    {
        $team = Team::factory()->create();
        Player::factory()->create(['team_id' => $team->id, 'shirt_number' => 4]);

        Livewire::test(PlayersRelationManager::class, [
            'ownerRecord' => $team,
            'pageClass' => EditTeam::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'name' => 'Duplicate Number Player',
                'position' => 'Defender',
                'shirt_number' => 4,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['shirt_number']);

        $this->assertSame(1, Player::where('team_id', $team->id)->where('shirt_number', 4)->count());
    }
}
