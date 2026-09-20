<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameEvent>
 */
class GameEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'player_id' => Player::factory(),
            'type' => GameEvent::TYPE_GOAL,
            'minute' => fake()->numberBetween(1, 90),
        ];
    }

    public function goal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => GameEvent::TYPE_GOAL,
        ]);
    }

    public function assist(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => GameEvent::TYPE_ASSIST,
        ]);
    }

    public function yellowCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => GameEvent::TYPE_YELLOW_CARD,
        ]);
    }

    public function redCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => GameEvent::TYPE_RED_CARD,
        ]);
    }

    /**
     * Carries its own goalkeeper: the default player_id draws a random
     * position, which GameEvent's guard would reject outright.
     */
    public function cleanSheet(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => GameEvent::TYPE_CLEAN_SHEET,
            'player_id' => Player::factory()->state(['position' => Player::POSITION_GOALKEEPER]),
            'minute' => null,
        ]);
    }
}
