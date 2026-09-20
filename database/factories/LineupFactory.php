<?php

namespace Database\Factories;

use App\Models\Lineup;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lineup>
 */
class LineupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'formation' => array_rand(Lineup::FORMATIONS),
        ];
    }
}
