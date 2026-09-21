<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Transfers\Pages\ListTransferHistory;
use App\Filament\Club\Resources\Transfers\TransferHistoryResource;
use App\Filament\Resources\Transfers\Pages\CreateTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use App\Models\User;
use App\Services\BudgetService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los traspasos en los dos paneles (Fase 12): el administrador los registra —y
 * registrarlos los ejecuta—, el técnico los consulta.
 */
class TransferPanelsTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $selling;

    private Club $buying;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->selling = Club::factory()->create(['name' => 'Manaos FC', 'initial_balance' => 1_000_000]);
        $this->buying = Club::factory()->create(['name' => 'Tapajós SC', 'initial_balance' => 1_000_000]);

        $sellingTeam = Team::factory()->create(['club_id' => $this->selling->id, 'season_id' => $this->season->id]);
        Team::factory()->create(['club_id' => $this->buying->id, 'season_id' => $this->season->id]);

        $this->player = Player::factory()->create(['club_id' => $this->selling->id, 'name' => 'Jugador Traspasado']);
        SquadMembership::factory()->create([
            'team_id' => $sellingTeam->id,
            'player_id' => $this->player->id,
            'shirt_number' => 7,
        ]);
    }

    public function test_the_administrator_records_a_transfer_and_it_executes(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateTransfer::class)
            ->fillForm([
                'player_id' => $this->player->id,
                'season_id' => $this->season->id,
                'scope' => Transfer::SCOPE_INTERNAL,
                'type' => Transfer::TYPE_SIGNING,
                'from_club_id' => $this->selling->id,
                'to_club_id' => $this->buying->id,
                'fee' => 200_000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1_200_000, app(BudgetService::class)->balanceFor($this->selling->fresh()));
        $this->assertSame(800_000, app(BudgetService::class)->balanceFor($this->buying->fresh()));
        $this->assertSame($this->buying->id, $this->player->fresh()->club_id);
    }

    /**
     * El formulario no ofrece registrar una venta entre clubes de la liga: esa
     * operación es el fichaje del comprador, una sola fila (design D8).
     */
    public function test_an_in_league_scope_does_not_offer_a_sale(): void
    {
        $this->actingAs(User::factory()->create());

        $options = Livewire::test(CreateTransfer::class)
            ->fillForm(['scope' => Transfer::SCOPE_INTERNAL])
            ->instance()
            ->getSchemaComponent('form.type')
            ->getOptions();

        $this->assertArrayNotHasKey(Transfer::TYPE_SALE, $options);
        $this->assertArrayHasKey(Transfer::TYPE_SIGNING, $options);
        $this->assertArrayHasKey(Transfer::TYPE_LOAN, $options);
    }

    public function test_the_administrator_lists_every_clubs_transfers(): void
    {
        $this->actingAs(User::factory()->create());

        $transfer = Transfer::factory()->create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
        ]);

        Livewire::test(ListTransfers::class)->assertCanSeeTableRecords([$transfer]);
    }

    public function test_the_coach_sees_both_sides_of_their_own_club(): void
    {
        $coach = User::factory()->coachOf($this->selling)->create();
        $this->actingAs($coach, 'club');
        Filament::setCurrentPanel('club');

        $out = Transfer::factory()->create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
        ]);

        $in = Transfer::factory()->create([
            'player_id' => Player::factory()->create(['club_id' => $this->buying->id])->id,
            'season_id' => $this->season->id,
            'from_club_id' => $this->buying->id,
            'to_club_id' => $this->selling->id,
        ]);

        $foreign = Transfer::factory()->create(['season_id' => $this->season->id]);

        Livewire::test(ListTransferHistory::class)
            ->assertCanSeeTableRecords([$out, $in])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    /**
     * El mismo traspaso es venta para quien cede y fichaje para quien recibe.
     */
    public function test_the_history_names_the_operation_from_the_clubs_side(): void
    {
        $coach = User::factory()->coachOf($this->selling)->create();
        $this->actingAs($coach, 'club');
        Filament::setCurrentPanel('club');

        $transfer = Transfer::factory()->create([
            'player_id' => $this->player->id,
            'season_id' => $this->season->id,
            'from_club_id' => $this->selling->id,
            'to_club_id' => $this->buying->id,
        ]);

        Livewire::test(ListTransferHistory::class)
            ->assertTableColumnStateSet('operacion', 'Venta', $transfer)
            ->assertTableColumnStateSet('contraparte', 'Tapajós SC', $transfer);
    }

    public function test_the_coach_cannot_record_a_transfer(): void
    {
        $coach = User::factory()->coachOf($this->selling)->create();

        $this->assertFalse(TransferHistoryResource::canCreate());
        $this->assertFalse(Gate::forUser($coach)->allows('create', Transfer::class));
        $this->assertTrue(Gate::forUser(User::factory()->create())->allows('create', Transfer::class));
    }
}
