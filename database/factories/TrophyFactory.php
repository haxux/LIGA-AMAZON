<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Season;
use App\Models\Trophy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trophy>
 */
class TrophyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'season_id' => Season::factory(),
            'name' => fake()->randomElement(['Liga', 'Copa', 'Supercopa', 'Torneo de Verano']),
        ];
    }
}
