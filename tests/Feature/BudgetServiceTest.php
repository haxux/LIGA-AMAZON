<?php

namespace Tests\Feature;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Season;
use App\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * El saldo de un club se deriva del libro de movimientos (Fase 12, design D7).
 */
class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Season $season;

    private BudgetService $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create(['initial_balance' => 1_000_000]);
        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->budget = app(BudgetService::class);
    }

    private function movement(string $type, int $amount, string $status): BudgetMovement
    {
        return BudgetMovement::factory()->create([
            'club_id' => $this->club->id,
            'season_id' => $this->season->id,
            'type' => $type,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    public function test_the_balance_starts_at_the_initial_one(): void
    {
        $this->assertSame(1_000_000, $this->budget->balanceFor($this->club));
    }

    public function test_approved_movements_move_the_balance(): void
    {
        $this->movement(BudgetMovement::TYPE_INCOME, 500_000, BudgetMovement::STATUS_APPROVED);
        $this->movement(BudgetMovement::TYPE_EXPENSE, 200_000, BudgetMovement::STATUS_APPROVED);

        $this->assertSame(1_300_000, $this->budget->balanceFor($this->club));
    }

    /**
     * Lo propuesto no cuenta: es exactamente lo que el propietario pidió al
     * cerrar la decisión del presupuesto.
     */
    public function test_proposed_and_rejected_movements_do_not(): void
    {
        $this->movement(BudgetMovement::TYPE_EXPENSE, 900_000, BudgetMovement::STATUS_PROPOSED);
        $this->movement(BudgetMovement::TYPE_EXPENSE, 900_000, BudgetMovement::STATUS_REJECTED);

        $this->assertSame(1_000_000, $this->budget->balanceFor($this->club));
    }

    public function test_approving_a_proposal_moves_the_balance(): void
    {
        $movement = $this->movement(BudgetMovement::TYPE_INCOME, 250_000, BudgetMovement::STATUS_PROPOSED);

        $this->assertSame(1_000_000, $this->budget->balanceFor($this->club));

        $movement->update(['status' => BudgetMovement::STATUS_APPROVED]);

        $this->assertSame(1_250_000, $this->budget->balanceFor($this->club));
    }

    public function test_another_clubs_movements_stay_out_of_it(): void
    {
        BudgetMovement::factory()->create([
            'season_id' => $this->season->id,
            'type' => BudgetMovement::TYPE_INCOME,
            'amount' => 5_000_000,
        ]);

        $this->assertSame(1_000_000, $this->budget->balanceFor($this->club));
    }

    /**
     * El saldo es acumulado: el dinero de un club no se reinicia en agosto. La
     * temporada del movimiento sirve para filtrar el libro, no para partirlo.
     */
    public function test_the_balance_carries_across_seasons(): void
    {
        $past = Season::factory()->create(['name' => '2025/26']);
        BudgetMovement::factory()->create([
            'club_id' => $this->club->id,
            'season_id' => $past->id,
            'type' => BudgetMovement::TYPE_INCOME,
            'amount' => 300_000,
        ]);

        $this->assertSame(1_300_000, $this->budget->balanceFor($this->club));
    }

    public function test_a_movement_of_zero_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->movement(BudgetMovement::TYPE_INCOME, 0, BudgetMovement::STATUS_APPROVED);
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->movement('regalo', 1000, BudgetMovement::STATUS_APPROVED);
    }

    public function test_pending_lists_only_what_awaits_an_answer(): void
    {
        $proposed = $this->movement(BudgetMovement::TYPE_EXPENSE, 100, BudgetMovement::STATUS_PROPOSED);
        $this->movement(BudgetMovement::TYPE_EXPENSE, 100, BudgetMovement::STATUS_APPROVED);

        $pending = $this->budget->pendingFor($this->club);

        $this->assertCount(1, $pending);
        $this->assertTrue($pending->first()->is($proposed));
    }
}
