<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\StandingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandingZone>
 */
class StandingZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'label' => 'Ascenso',
            'color' => 'green',
            'from_position' => 1,
            'to_position' => 2,
        ];
    }
}
