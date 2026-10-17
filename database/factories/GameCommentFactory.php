<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameComment>
 */
class GameCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
