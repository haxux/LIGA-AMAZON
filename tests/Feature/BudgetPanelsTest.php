<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Budget\BudgetResource;
use App\Filament\Club\Resources\Budget\Pages\ListBudget;
use App\Filament\Resources\BudgetMovements\Pages\CreateBudgetMovement;
use App\Filament\Resources\BudgetMovements\Pages\ListBudgetMovements;
use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Season;
use App\Models\User;
use App\Services\BudgetService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El libro de movimientos: el administrador lo escribe y el técnico lo lee.
 *
 * Lo que el técnico propone son fichajes, no movimientos sueltos —una propuesta
 * de ingreso o egreso sin la operación detrás dejaba al administrador
 * adivinando de qué era—, y eso vive en `TransferProposalTest`.
 */
class BudgetPanelsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create(['initial_balance' => 500_000]);
        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
    }

    private function asCoach(): User
    {
        $coach = User::factory()->coachOf($this->club)->create();
        $this->actingAs($coach, 'club');
        Filament::setCurrentPanel('club');

        return $coach;
    }

    /**
     * El técnico ya no propone movimientos sueltos: lo que propone es el
     * fichaje entero, desde su módulo de Fichajes, y es esa operación la que
     * explica el dinero (ver TransferProposalTest). Aquí sólo lee.
     */
    public function test_the_coach_cannot_propose_a_movement_any_more(): void
    {
        $this->asCoach();

        $this->assertFalse(BudgetResource::canCreate());
        $this->get('/club/contabilidad/create')->assertNotFound();
        $this->assertSame(0, BudgetMovement::query()->count());
    }

    public function test_the_coach_only_sees_their_own_book(): void
    {
        $this->asCoach();

        $mine = BudgetMovement::factory()->create(['club_id' => $this->club->id, 'season_id' => $this->season->id]);
        $theirs = BudgetMovement::factory()->create(['season_id' => $this->season->id]);

        Livewire::test(ListBudget::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_coach_reads_their_balance_over_the_table(): void
    {
        $this->asCoach();

        BudgetMovement::factory()->create([
            'club_id' => $this->club->id,
            'season_id' => $this->season->id,
            'type' => BudgetMovement::TYPE_INCOME,
            'amount' => 250_000,
            'status' => BudgetMovement::STATUS_APPROVED,
        ]);

        $this->assertSame('Saldo: 750.000', Livewire::test(ListBudget::class)->instance()->getSubheading());
    }

    /**
     * Aprobar es del administrador: el panel del técnico no ofrece la acción ni
     * la edición, y su recurso no registra esas páginas.
     */
    public function test_the_coach_cannot_approve_or_edit(): void
    {
        $this->asCoach();

        $movement = BudgetMovement::factory()->proposed()->create([
            'club_id' => $this->club->id,
            'season_id' => $this->season->id,
        ]);

        $this->get("/club/contabilidad/{$movement->getKey()}/edit")->assertNotFound();

        Livewire::test(ListBudget::class)->assertTableActionDoesNotExist('approve');
    }

    public function test_the_coach_cannot_open_another_clubs_movement(): void
    {
        $this->asCoach();

        $theirs = BudgetMovement::factory()->create(['season_id' => $this->season->id]);

        Livewire::test(ListBudget::class)->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_administrator_approves_a_proposal_and_the_balance_moves(): void
    {
        $this->actingAs(User::factory()->create());

        $movement = BudgetMovement::factory()->proposed()->create([
            'club_id' => $this->club->id,
            'season_id' => $this->season->id,
            'type' => BudgetMovement::TYPE_INCOME,
            'amount' => 300_000,
        ]);

        Livewire::test(ListBudgetMovements::class)
            ->callTableAction('approve', $movement)
            ->assertHasNoTableActionErrors();

        $this->assertSame(BudgetMovement::STATUS_APPROVED, $movement->fresh()->status);
        $this->assertSame(800_000, app(BudgetService::class)->balanceFor($this->club->fresh()));
    }

    public function test_the_administrator_rejects_a_proposal_without_losing_it(): void
    {
        $this->actingAs(User::factory()->create());

        $movement = BudgetMovement::factory()->proposed()->create([
            'club_id' => $this->club->id,
            'season_id' => $this->season->id,
        ]);

        Livewire::test(ListBudgetMovements::class)->callTableAction('reject', $movement);

        $this->assertSame(BudgetMovement::STATUS_REJECTED, $movement->fresh()->status);
        $this->assertDatabaseHas('budget_movements', ['id' => $movement->id]);
    }

    /**
     * Lo que el administrador carga de su mano nace aprobado: no tiene a quién
     * pedirle permiso.
     */
    public function test_what_the_administrator_records_is_approved_by_default(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateBudgetMovement::class)
            ->fillForm([
                'club_id' => $this->club->id,
                'season_id' => $this->season->id,
                'type' => BudgetMovement::TYPE_INCOME,
                'amount' => 400_000,
                'reason' => 'Patrocinio',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(BudgetMovement::STATUS_APPROVED, BudgetMovement::query()->latest('id')->first()->status);
        $this->assertSame(900_000, app(BudgetService::class)->balanceFor($this->club->fresh()));
    }
}
