<?php

namespace Database\Factories;

use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matchday>
 */
class MatchdayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'number' => fake()->numberBetween(1, 18),
            'date' => fake()->dateTimeBetween('-2 months', '+4 months')->format('Y-m-d'),
        ];
    }
}
