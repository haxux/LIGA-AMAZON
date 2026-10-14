<?php

namespace Database\Factories;

use App\Enums\CupDesign;
use App\Enums\CupScope;
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
            'scope' => CupScope::National,
            'has_group_stage' => false,
        ];
    }

    public function international(CupDesign $design = CupDesign::Libertadores): static
    {
        return $this->state(fn () => ['scope' => CupScope::International, 'design' => $design]);
    }

    public function withGroups(): static
    {
        return $this->state(fn () => ['has_group_stage' => true]);
    }
}
