<?php

namespace Database\Factories;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetMovement>
 */
class BudgetMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'season_id' => Season::factory(),
            'type' => BudgetMovement::TYPE_INCOME,
            'amount' => fake()->numberBetween(1000, 5_000_000),
            'reason' => fake()->sentence(3),
            'status' => BudgetMovement::STATUS_APPROVED,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => BudgetMovement::TYPE_EXPENSE]);
    }

    public function proposed(): static
    {
        return $this->state(fn () => ['status' => BudgetMovement::STATUS_PROPOSED]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => BudgetMovement::STATUS_REJECTED]);
    }
}
