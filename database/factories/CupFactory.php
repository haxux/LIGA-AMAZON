<?php

namespace Database\Factories;

use App\Models\Cup;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cup>
 */
class CupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'name' => 'Copa Amazonas',
            'has_group_stage' => false,
        ];
    }

    public function withGroups(): static
    {
        return $this->state(fn () => ['has_group_stage' => true]);
    }
}
