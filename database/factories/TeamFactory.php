<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Curated demo roster (Fase-5's "Primera" division) — deliberately not
     * Faker's company/person generators, which read poorly for a football
     * club identity. Shared with DatabaseSeeder for the exact demo roster.
     *
     * @var array<string, array{short: string, city: string}>
     */
    public const CLUBS = [
        'Manaos FC' => ['short' => 'MAN', 'city' => 'Manaus'],
        'Tapajós SC' => ['short' => 'TAP', 'city' => 'Santarém'],
        'Río Negro CF' => ['short' => 'RIN', 'city' => 'Tabatinga'],
        'Amazonas Royals' => ['short' => 'AMZ', 'city' => 'Macapá'],
        'Belém Athletic' => ['short' => 'BEL', 'city' => 'Belém'],
        'Madeira City' => ['short' => 'MAD', 'city' => 'Porto Velho'],
        'Xingu Rangers' => ['short' => 'XIN', 'city' => 'Altamira'],
        'Solimões FC' => ['short' => 'SOL', 'city' => 'Tefé'],
        'Iquitos United' => ['short' => 'IQU', 'city' => 'Iquitos'],
        'Marañón AC' => ['short' => 'MAR', 'city' => 'Yurimaguas'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(array_keys(self::CLUBS));

        return [
            'season_id' => Season::factory(),
            'division_id' => null,
            'name' => $name,
            'short_name' => self::CLUBS[$name]['short'],
            'crest_path' => null,
            'founded_year' => fake()->numberBetween(1900, 2015),
        ];
    }
}
