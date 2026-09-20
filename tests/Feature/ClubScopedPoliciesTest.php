<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Stadium;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * El pendiente 4.1 de DESPLIEGUE.md, cerrado: hasta la Fase 9 cualquier usuario
 * del panel podía editar cualquier fila. Con un único operador era inofensivo;
 * con un técnico dentro, no.
 */
class ClubScopedPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_coach_may_edit_their_own_club_and_not_another(): void
    {
        $own = Club::factory()->create();
        $other = Club::factory()->create();
        $coach = User::factory()->coachOf($own)->create();

        $this->assertTrue(Gate::forUser($coach)->allows('update', $own));
        $this->assertFalse(Gate::forUser($coach)->allows('update', $other));
    }

    public function test_a_coach_may_edit_their_own_players_and_not_another_clubs(): void
    {
        $own = Club::factory()->create();
        $coach = User::factory()->coachOf($own)->create();
        $ownPlayer = Player::factory()->create(['club_id' => $own->id]);
        $foreignPlayer = Player::factory()->create();

        $this->assertTrue(Gate::forUser($coach)->allows('update', $ownPlayer));
        $this->assertFalse(Gate::forUser($coach)->allows('update', $foreignPlayer));
        $this->assertFalse(Gate::forUser($coach)->allows('delete', $foreignPlayer));
    }

    public function test_a_coach_may_edit_their_own_teams_stadium_and_squad(): void
    {
        $own = Club::factory()->create();
        $coach = User::factory()->coachOf($own)->create();
        $team = Team::factory()->create(['club_id' => $own->id, 'season_id' => Season::factory()]);
        $stadium = Stadium::factory()->create(['club_id' => $own->id]);
        $membership = SquadMembership::factory()->create(['team_id' => $team->id]);

        $this->assertTrue(Gate::forUser($coach)->allows('update', $team));
        $this->assertTrue(Gate::forUser($coach)->allows('update', $stadium));
        $this->assertTrue(Gate::forUser($coach)->allows('update', $membership));
    }

    /**
     * En una cesión manda el club donde el jugador juega, no el propietario:
     * la plantilla la arma quien la alinea.
     */
    public function test_a_loaned_players_membership_belongs_to_the_club_fielding_them(): void
    {
        $fielding = Club::factory()->create();
        $owning = Club::factory()->create();
        $fieldingCoach = User::factory()->coachOf($fielding)->create();
        $owningCoach = User::factory()->coachOf($owning)->create();

        $team = Team::factory()->create(['club_id' => $fielding->id]);
        $loanee = Player::factory()->create(['club_id' => $owning->id]);
        $membership = SquadMembership::factory()->create([
            'team_id' => $team->id,
            'player_id' => $loanee->id,
            'type' => SquadMembership::TYPE_LOAN,
        ]);

        $this->assertTrue(Gate::forUser($fieldingCoach)->allows('update', $membership));
        $this->assertFalse(Gate::forUser($owningCoach)->allows('update', $membership));
    }

    public function test_an_administrator_passes_everywhere(): void
    {
        $admin = User::factory()->create();
        $club = Club::factory()->create();
        $player = Player::factory()->create();
        $team = Team::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('update', $club));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $player));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $team));
    }
}
