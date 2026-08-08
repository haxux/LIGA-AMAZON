<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Matchday;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * home_team_id and away_team_id each resolve via an independent
     * Team::factory() call, so they are distinct teams by construction —
     * the default state never trips the Game::booted() guard.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matchday_id' => Matchday::factory(),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'kickoff_at' => null,
            'home_score' => null,
            'away_score' => null,
        ];
    }

    /**
     * A concluded game with both scores set.
     */
    public function played(): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score' => fake()->numberBetween(0, 5),
            'away_score' => fake()->numberBetween(0, 5),
        ]);
    }
}
