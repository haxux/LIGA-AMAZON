<?php

namespace Database\Factories;

use App\Models\Division;
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
            'division_id' => fn (array $attributes) => self::divisionForSeason($attributes['season_id']),
            'number' => fn (array $attributes) => self::nextNumberForDivision($attributes['division_id']),
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
                'number' => fn (array $attributes) => self::nextNumberForDivision($attributes['division_id'], $offset),
            ];
        });
    }

    /**
     * Numbering is per division now that each one runs its own calendar, so
     * Primera and Segunda can both hold a Jornada 1.
     */
    private static function nextNumberForDivision(int|string $divisionId, int $offset = 0): int
    {
        return (int) Matchday::where('division_id', $divisionId)->max('number') + 1 + $offset;
    }

    /**
     * Reuses the season's first division instead of minting one per matchday:
     * DivisionFactory draws its name from a two-value unique pool, so a
     * division per matchday would exhaust it within a single test — and a
     * season's jornadas belong to a handful of divisions, not one each.
     */
    private static function divisionForSeason(int|string $seasonId): int
    {
        return Division::query()->where('season_id', $seasonId)->orderBy('id')->value('id')
            ?? Division::factory()->create(['season_id' => $seasonId])->getKey();
    }
}
