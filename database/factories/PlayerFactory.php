<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    private const POSITIONS = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'position' => fake()->randomElement(self::POSITIONS),
            'birth_date' => fake()->dateTimeBetween('-38 years', '-16 years')->format('Y-m-d'),
            'shirt_number' => fake()->numberBetween(1, 18),
        ];
    }

    /**
     * Assign distinct, sequential shirt numbers (1, 2, 3, ...) so that
     * calling ->count(N)->create() for one team never collides with the
     * unique(team_id, shirt_number) constraint.
     */
    public function configure(): static
    {
        return $this->sequence(fn (Sequence $sequence) => ['shirt_number' => $sequence->index + 1]);
    }
}
