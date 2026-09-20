<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(array_keys(TeamFactory::CLUBS));

        return [
            'name' => $name,
            'short_name' => TeamFactory::CLUBS[$name]['short'],
            'crest_path' => null,
            'founded_year' => fake()->numberBetween(1900, 2015),
        ];
    }
}
