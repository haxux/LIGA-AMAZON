<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'season_id' => Season::factory(),
            'type' => Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            'from_club_id' => Club::factory(),
            'to_club_id' => Club::factory(),
            'external_club' => null,
            'fee' => fake()->numberBetween(10_000, 2_000_000),
            'loan_term' => null,
        ];
    }

    public function loan(): static
    {
        return $this->state(fn () => [
            'type' => Transfer::TYPE_LOAN,
            'fee' => 0,
            'loan_term' => Transfer::TERM_ONE_YEAR,
        ]);
    }

    public function external(): static
    {
        return $this->state(fn () => [
            'scope' => Transfer::SCOPE_EXTERNAL,
            'from_club_id' => null,
            'external_club' => 'Club de Fuera',
        ]);
    }
}
