<?php

namespace Database\Factories;

use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<Matchday>
 */
class MatchdayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'number' => fn (array $attributes) => self::nextNumberForSeason($attributes['season_id']),
            'date' => fake()->dateTimeBetween('-2 months', '+4 months')->format('Y-m-d'),
        ];
    }

    /**
     * The DB lookup carries uniqueness ACROSS separate create() calls; the sequence
     * index carries it WITHIN one ->count(N)->create() batch, where every instance is
     * built before any is persisted. Mirrors PlayerFactory::configure()'s precedent.
     */
    public function configure(): static
    {
        return $this->sequence(function (Sequence $sequence) {
            // Capture the offset now: Sequence::__invoke() increments
            // $sequence->index right after this callback returns, but the
            // 'number' closure below is only evaluated later during
            // attribute expansion — reading $sequence->index lazily there
            // would see the already-incremented value (off-by-one).
            $offset = $sequence->index;

            return [
                'number' => fn (array $attributes) => self::nextNumberForSeason($attributes['season_id'], $offset),
            ];
        });
    }

    private static function nextNumberForSeason(int|string $seasonId, int $offset = 0): int
    {
        return (int) Matchday::where('season_id', $seasonId)->max('number') + 1 + $offset;
    }
}
