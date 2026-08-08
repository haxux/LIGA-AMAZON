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
}
