<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Budget\BudgetResource;
use App\Filament\Club\Resources\Transfers\Pages\ProposeTransfer;
use App\Filament\Club\Resources\Transfers\Schemas\TransferProposalForm;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\TransferService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El técnico propone el fichaje entero —jugador, importe y ámbito— desde su
 * módulo de Fichajes, y el administrador lo firma o lo rechaza. Hasta la firma
 * no se mueve ni la plantilla ni el dinero.
 */
class TransferProposalTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $mine;

    private Club $theirs;

    private Team $myTeam;

    private Team $theirTeam;

    private User $coach;

    private Player $theirPlayer;

    private Player $myPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->mine = Club::factory()->create(['name' => 'Manaos FC', 'initial_balance' => 1_000_000]);
        $this->theirs = Club::factory()->create(['name' => 'Tapajós SC', 'initial_balance' => 1_000_000]);

        $this->myTeam = Team::factory()->create(['club_id' => $this->mine->id, 'season_id' => $this->season->id]);
        $this->theirTeam = Team::factory()->create(['club_id' => $this->theirs->id, 'season_id' => $this->season->id]);

        $this->coach = User::factory()->coachOf($this->mine)->create();

        $this->theirPlayer = Player::factory()->create(['club_id' => $this->theirs->id, 'name' => 'El Fichaje']);
        SquadMembership::factory()->create(['team_id' => $this->theirTeam->id, 'player_id' => $this->theirPlayer->id, 'shirt_number' => 9]);

        $this->myPlayer = Player::factory()->create(['club_id' => $this->mine->id, 'name' => 'El Mío']);
        SquadMembership::factory()->create(['team_id' => $this->myTeam->id, 'player_id' => $this->myPlayer->id, 'shirt_number' => 4]);
    }

    private function asCoach(): void
    {
        $this->actingAs($this->coach, 'club');
        Filament::setCurrentPanel('club');
    }

    private function balance(Club $club): int
    {
        return app(BudgetService::class)->balanceFor($club->fresh());
    }

    public function test_the_coach_proposes_a_signing_and_nothing_moves_yet(): void
    {
        $this->asCoach();

        Livewire::test(ProposeTransfer::class)
            ->fillForm([
                'direction' => TransferProposalForm::IN,
                'scope' => Transfer::SCOPE_INTERNAL,
                'type' => Transfer::TYPE_SIGNING,
                'player_id' => $this->theirPlayer->id,
                'fee' => 300_000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $proposal = Transfer::query()->latest('id')->first();

        // Los dos extremos salen de la dirección y del club del jugador.
        $this->assertSame(Transfer::STATUS_PROPOSED, $proposal->status);
        $this->assertSame($this->theirs->id, $proposal->from_club_id);
        $this->assertSame($this->mine->id, $proposal->to_club_id);
        $this->assertSame($this->season->id, $proposal->season_id);
        $this->assertSame($this->coach->id, $proposal->proposed_by);

        // Y no ha movido nada: ni dinero, ni plantilla, ni propiedad.
        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertSame($this->theirs->id, $this->theirPlayer->fresh()->club_id);
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->theirTeam->id,
            'player_id' => $this->theirPlayer->id,
        ]);
    }

    public function test_the_coach_proposes_letting_one_of_theirs_go(): void
    {
        $this->asCoach();

        Livewire::test(ProposeTransfer::class)
            ->fillForm([
                'direction' => TransferProposalForm::OUT,
                'scope' => Transfer::SCOPE_EXTERNAL,
                'type' => Transfer::TYPE_SALE,
                'player_id' => $this->myPlayer->id,
                'external_club' => 'Palmeiras',
                'fee' => 500_000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $proposal = Transfer::query()->latest('id')->first();

        $this->assertSame($this->mine->id, $proposal->from_club_id);
        $this->assertNull($proposal->to_club_id);
        $this->assertSame('Palmeiras', $proposal->external_club);
        $this->assertNull($this->myPlayer->fresh()->left_at);
    }

    /**
     * Traer a alguien de fuera exige darle ficha antes, y las fichas las crea
     * el administrador: al técnico no se le ofrece esa combinación.
     */
    public function test_an_incoming_transfer_from_outside_is_not_offered(): void
    {
        $this->asCoach();

        $scopes = Livewire::test(ProposeTransfer::class)
            ->fillForm(['direction' => TransferProposalForm::IN])
            ->instance()
            ->getSchemaComponent('form.scope')
            ->getOptions();

        $this->assertSame([Transfer::SCOPE_INTERNAL => Transfer::SCOPES[Transfer::SCOPE_INTERNAL]], $scopes);
    }

    /**
     * Una venta entre clubes de la liga es el fichaje del comprador: una fila,
     * leída desde los dos lados (design D8).
     */
    public function test_an_internal_sale_is_not_among_the_types(): void
    {
        $this->assertArrayNotHasKey(
            Transfer::TYPE_SALE,
            TransferProposalForm::typesFor(TransferProposalForm::OUT, Transfer::SCOPE_INTERNAL),
        );

        $this->assertArrayHasKey(
            Transfer::TYPE_SALE,
            TransferProposalForm::typesFor(TransferProposalForm::OUT, Transfer::SCOPE_EXTERNAL),
        );
    }

    public function test_the_coach_only_offers_players_of_the_right_side(): void
    {
        $this->asCoach();

        $this->assertArrayHasKey($this->theirPlayer->id, TransferProposalForm::playersElsewhere());
        $this->assertArrayNotHasKey($this->myPlayer->id, TransferProposalForm::playersElsewhere());

        $this->assertArrayHasKey($this->myPlayer->id, TransferProposalForm::ownPlayers());
        $this->assertArrayNotHasKey($this->theirPlayer->id, TransferProposalForm::ownPlayers());
    }

    public function test_the_administrator_signs_it_and_then_everything_moves(): void
    {
        $proposal = Transfer::create([
            'player_id' => $this->theirPlayer->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_PROPOSED,
            'from_club_id' => $this->theirs->id,
            'to_club_id' => $this->mine->id,
            'fee' => 300_000,
            'proposed_by' => $this->coach->id,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ListTransfers::class)
            ->callTableAction('approve', $proposal)
            ->assertHasNoTableActionErrors();

        $this->assertSame(Transfer::STATUS_EXECUTED, $proposal->fresh()->status);
        $this->assertSame(700_000, $this->balance($this->mine));
        $this->assertSame(1_300_000, $this->balance($this->theirs));
        $this->assertSame($this->mine->id, $this->theirPlayer->fresh()->club_id);
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->myTeam->id,
            'player_id' => $this->theirPlayer->id,
        ]);
    }

    /**
     * Rechazar conserva la fila: qué pidió el técnico y qué se le respondió es
     * historia del club.
     */
    public function test_rejecting_keeps_the_record_and_moves_nothing(): void
    {
        $proposal = Transfer::create([
            'player_id' => $this->theirPlayer->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_PROPOSED,
            'from_club_id' => $this->theirs->id,
            'to_club_id' => $this->mine->id,
            'fee' => 300_000,
        ]);

        app(TransferService::class)->reject($proposal, User::factory()->create());

        $this->assertSame(Transfer::STATUS_REJECTED, $proposal->fresh()->status);
        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertDatabaseHas('transfers', ['id' => $proposal->id]);
    }

    public function test_only_an_administrator_signs_and_only_a_proposal(): void
    {
        $proposal = Transfer::create([
            'player_id' => $this->theirPlayer->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_PROPOSED,
            'from_club_id' => $this->theirs->id,
            'to_club_id' => $this->mine->id,
            'fee' => 1,
        ]);

        try {
            app(TransferService::class)->approve($proposal, $this->coach);
            $this->fail('un técnico no debería poder firmar su propia propuesta');
        } catch (ValidationException) {
            $this->assertSame(Transfer::STATUS_PROPOSED, $proposal->fresh()->status);
        }

        $admin = User::factory()->create();
        app(TransferService::class)->approve($proposal->fresh(), $admin);

        $this->expectException(ValidationException::class);
        app(TransferService::class)->approve($proposal->fresh(), $admin);
    }

    /**
     * Lo que el administrador registra de su mano sigue naciendo ejecutado: no
     * tiene a quién pedirle permiso.
     */
    public function test_what_the_administrator_records_executes_at_once(): void
    {
        Transfer::create([
            'player_id' => $this->theirPlayer->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'from_club_id' => $this->theirs->id,
            'to_club_id' => $this->mine->id,
            'fee' => 200_000,
        ]);

        $this->assertSame(800_000, $this->balance($this->mine));
        $this->assertSame($this->mine->id, $this->theirPlayer->fresh()->club_id);
    }

    public function test_the_pending_proposals_show_on_the_admin_navigation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertNull(TransferResource::getNavigationBadge());

        Transfer::create([
            'player_id' => $this->theirPlayer->id,
            'season_id' => $this->season->id,
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'status' => Transfer::STATUS_PROPOSED,
            'from_club_id' => $this->theirs->id,
            'to_club_id' => $this->mine->id,
            'fee' => 1,
        ]);

        $this->assertSame('1', TransferResource::getNavigationBadge());
    }

    /**
     * Y el presupuesto deja de aceptar propuestas sueltas: lo que el técnico
     * propone es la operación entera, que es la que explica el dinero.
     */
    public function test_the_budget_no_longer_takes_proposals(): void
    {
        $this->asCoach();

        $this->assertFalse(BudgetResource::canCreate());
        $this->get('/club/contabilidad/create')->assertNotFound();
        $this->assertTrue(Gate::forUser($this->coach)->denies('create', Transfer::class));
    }
}
