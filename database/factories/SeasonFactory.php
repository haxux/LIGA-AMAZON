<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2099);

        return [
            'name' => sprintf('%d/%02d', $startYear, ($startYear + 1) % 100),
            'start_date' => sprintf('%d-08-01', $startYear),
            'end_date' => sprintf('%d-05-31', $startYear + 1),
        ];
    }
}
