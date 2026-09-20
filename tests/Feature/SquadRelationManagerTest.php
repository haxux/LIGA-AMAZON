<?php

namespace Tests\Feature;

use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\RelationManagers\SquadRelationManager;
use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La plantilla de una temporada: quién la compone, con qué dorsal y a qué
 * título. La identidad de cada jugador vive en el club (Fase 9).
 */
class SquadRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function manager(Team $team): Testable
    {
        return Livewire::test(SquadRelationManager::class, [
            'ownerRecord' => $team,
            'pageClass' => EditTeam::class,
        ]);
    }

    public function test_relation_manager_renders(): void
    {
        $this->manager(Team::factory()->create())->assertOk();
    }

    public function test_lists_only_this_seasons_squad(): void
    {
        $club = Club::factory()->create();
        $thisSeason = Team::factory()->create(['club_id' => $club->id]);
        $lastSeason = Team::factory()->create(['club_id' => $club->id]);
        $current = SquadMembership::factory()->create(['team_id' => $thisSeason->id]);
        $past = SquadMembership::factory()->create(['team_id' => $lastSeason->id]);

        $this->manager($thisSeason)
            ->assertCanSeeTableRecords([$current])
            ->assertCanNotSeeTableRecords([$past]);
    }

    public function test_a_player_can_be_added_to_the_squad(): void
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create(['club_id' => $team->club_id]);

        $this->manager($team)
            ->mountTableAction('create')
            ->setTableActionData([
                'player_id' => $player->id,
                'shirt_number' => 9,
                'type' => SquadMembership::TYPE_OWNED,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $team->id,
            'player_id' => $player->id,
            'shirt_number' => 9,
        ]);
    }

    public function test_duplicate_shirt_number_within_the_squad_is_rejected_as_a_form_error(): void
    {
        $team = Team::factory()->create();
        SquadMembership::factory()->create(['team_id' => $team->id, 'shirt_number' => 4]);
        $player = Player::factory()->create(['club_id' => $team->club_id]);

        $this->manager($team)
            ->mountTableAction('create')
            ->setTableActionData([
                'player_id' => $player->id,
                'shirt_number' => 4,
                'type' => SquadMembership::TYPE_OWNED,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['shirt_number']);

        $this->assertSame(1, SquadMembership::where('team_id', $team->id)->where('shirt_number', 4)->count());
    }

    /**
     * El mismo dorsal en otra temporada del mismo club es correcto: es el
     * defecto que la Fase 9 arregla.
     */
    public function test_the_same_shirt_number_in_another_season_is_allowed(): void
    {
        $club = Club::factory()->create();
        $lastSeason = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()]);
        $thisSeason = Team::factory()->create(['club_id' => $club->id, 'season_id' => Season::factory()]);
        SquadMembership::factory()->create(['team_id' => $lastSeason->id, 'shirt_number' => 10]);
        $player = Player::factory()->create(['club_id' => $club->id]);

        $this->manager($thisSeason)
            ->mountTableAction('create')
            ->setTableActionData([
                'player_id' => $player->id,
                'shirt_number' => 10,
                'type' => SquadMembership::TYPE_OWNED,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, SquadMembership::where('shirt_number', 10)->count());
    }

    public function test_a_player_cannot_be_in_the_same_squad_twice(): void
    {
        $team = Team::factory()->create();
        $player = Player::factory()->create(['club_id' => $team->club_id]);
        SquadMembership::factory()->create(['team_id' => $team->id, 'player_id' => $player->id, 'shirt_number' => 3]);

        $this->manager($team)
            ->mountTableAction('create')
            ->setTableActionData([
                'player_id' => $player->id,
                'shirt_number' => 12,
                'type' => SquadMembership::TYPE_OWNED,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['player_id']);
    }

    public function test_a_loan_records_a_player_owned_by_another_club(): void
    {
        $team = Team::factory()->create();
        $loanee = Player::factory()->create();

        $this->manager($team)
            ->mountTableAction('create')
            ->setTableActionData([
                'player_id' => $loanee->id,
                'shirt_number' => 21,
                'type' => SquadMembership::TYPE_LOAN,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $team->id,
            'player_id' => $loanee->id,
            'type' => SquadMembership::TYPE_LOAN,
        ]);
        $this->assertNotSame($team->club_id, $loanee->club_id);
    }
}
