<?php

namespace Tests\Feature;

use App\Filament\Resources\Teams\RelationManagers\SquadRelationManager;
use App\Filament\Resources\Teams\TeamResource;
use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El fin de una cesión y la baja de plantilla.
 *
 * Ninguna de las dos existía: el plazo de una cesión era un texto que no vencía
 * ni servía para calcular nada, y para sacar a alguien de una plantilla sólo
 * había un botón de BORRAR —que destruía la constancia de que estuvo allí y con
 * ella la posibilidad de anotarle eventos de los partidos que jugó—.
 */
class EndLoanTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $owner;

    private Club $borrower;

    private Team $ownerTeam;

    private Team $borrowerTeam;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true]);
        $this->owner = Club::factory()->create(['name' => 'Club Dueño']);
        $this->borrower = Club::factory()->create(['name' => 'Club Prestatario']);

        $this->ownerTeam = Team::factory()->create(['club_id' => $this->owner->id, 'season_id' => $this->season->id]);
        $this->borrowerTeam = Team::factory()->create(['club_id' => $this->borrower->id, 'season_id' => $this->season->id]);

        // Un cedido: su club sigue siendo el dueño, pero juega en el otro.
        $this->player = Player::factory()->create(['club_id' => $this->owner->id, 'name' => 'El Cedido']);
    }

    private function loanHim(): SquadMembership
    {
        // Como queda tras una cesión: cerrada en el dueño, abierta y de tipo
        // cesión en quien lo recibe.
        SquadMembership::factory()->create([
            'team_id' => $this->ownerTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => null,
            'left_at' => now()->subMonth(),
        ]);

        return SquadMembership::factory()->create([
            'team_id' => $this->borrowerTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 20,
            'type' => SquadMembership::TYPE_LOAN,
        ]);
    }

    public function test_ending_a_loan_returns_him_to_his_owners_squad(): void
    {
        $loan = $this->loanHim();

        $home = app(TransferService::class)->endLoan($loan);

        $this->assertTrue($home->is($this->ownerTeam));
        $this->assertSame(0, $this->borrowerTeam->memberships()->count(), 'Sale de la plantilla prestataria');
        $this->assertSame(1, $this->ownerTeam->memberships()->count(), 'Y vuelve a la de su club');
        $this->assertSame(SquadMembership::TYPE_OWNED, $this->ownerTeam->memberships()->first()->type);
    }

    /**
     * Lo que estuvo en el club prestatario NO se borra: su fila se cierra, que
     * es lo que permite anotarle después un evento de un partido que jugó allí.
     */
    public function test_ending_a_loan_keeps_the_record_of_his_time_there(): void
    {
        $loan = $this->loanHim();

        app(TransferService::class)->endLoan($loan);

        $this->assertSame(1, $this->borrowerTeam->allMemberships()->count());
        $this->assertNotNull($loan->fresh()->left_at);
        $this->assertNull($loan->fresh()->shirt_number, 'Y suelta el dorsal');
    }

    /**
     * Vuelve a su fila de siempre, reabierta, y no a una segunda: (team_id,
     * player_id) es único y crear otra reventaría.
     */
    public function test_he_returns_to_his_old_row_reopened(): void
    {
        $loan = $this->loanHim();

        app(TransferService::class)->endLoan($loan);

        $this->assertSame(
            1,
            SquadMembership::query()->where('team_id', $this->ownerTeam->id)->where('player_id', $this->player->id)->count(),
        );
    }

    public function test_a_player_who_is_not_on_loan_cannot_be_returned(): void
    {
        $propio = SquadMembership::factory()->create([
            'team_id' => $this->ownerTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 5,
            'type' => SquadMembership::TYPE_OWNED,
        ]);

        $this->expectException(ValidationException::class);
        app(TransferService::class)->endLoan($propio);
    }

    /**
     * Si su club no está inscrito esa temporada no hay plantilla a la que
     * volver, pero la cesión se acabó igual: dejarlo en la ajena sería peor.
     */
    public function test_the_loan_closes_even_when_there_is_nowhere_to_return_to(): void
    {
        $huerfano = Player::factory()->create(['club_id' => Club::factory()->create()->id]);
        $loan = SquadMembership::factory()->create([
            'team_id' => $this->borrowerTeam->id,
            'player_id' => $huerfano->id,
            'shirt_number' => 21,
            'type' => SquadMembership::TYPE_LOAN,
        ]);

        $this->assertNull(app(TransferService::class)->endLoan($loan));
        $this->assertNotNull($loan->fresh()->left_at);
    }

    // ── Y la baja desde el panel ──────────────────────────────────────────

    /**
     * El botón ya no BORRA. Era la última puerta por la que se perdía la
     * constancia de que un jugador estuvo en una plantilla.
     */
    public function test_releasing_from_the_panel_closes_instead_of_deleting(): void
    {
        $this->actingAs(User::factory()->create());

        $membership = SquadMembership::factory()->create([
            'team_id' => $this->ownerTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 5,
        ]);

        Livewire::test(SquadRelationManager::class, [
            'ownerRecord' => $this->ownerTeam,
            'pageClass' => TeamResource\Pages\EditTeam::class,
        ])
            ->callTableAction('release', $membership)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('squad_memberships', ['id' => $membership->id]);
        $this->assertNotNull($membership->fresh()->left_at);
        $this->assertSame(0, $this->ownerTeam->memberships()->count());
    }

    public function test_the_panel_no_longer_offers_a_delete_action(): void
    {
        $this->actingAs(User::factory()->create());

        $membership = SquadMembership::factory()->create([
            'team_id' => $this->ownerTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 5,
        ]);

        Livewire::test(SquadRelationManager::class, [
            'ownerRecord' => $this->ownerTeam,
            'pageClass' => TeamResource\Pages\EditTeam::class,
        ])->assertTableActionDoesNotExist('delete');

        $this->assertDatabaseHas('squad_memberships', ['id' => $membership->id]);
    }
}
