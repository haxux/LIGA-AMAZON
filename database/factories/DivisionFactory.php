<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
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
            'name' => fake()->unique()->randomElement(['Primera', 'Segunda']),
        ];
    }
}
