<?php

namespace Database\Factories;

use App\Models\Stadium;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stadium>
 */
class StadiumFactory extends Factory
{
    /**
     * Curated Amazon-basin stadium/city pairs — not raw Faker for domain fields.
     *
     * @var array<int, array{name: string, city: string}>
     */
    private const STADIUMS = [
        ['name' => 'Arena Manaus', 'city' => 'Manaus'],
        ['name' => 'Estádio Tapajós', 'city' => 'Santarém'],
        ['name' => 'Estádio Río Negro', 'city' => 'Tabatinga'],
        ['name' => 'Arena Amazonas', 'city' => 'Macapá'],
        ['name' => 'Estádio Belém', 'city' => 'Belém'],
        ['name' => 'Arena Madeira', 'city' => 'Porto Velho'],
        ['name' => 'Estádio Xingu', 'city' => 'Altamira'],
        ['name' => 'Arena Solimões', 'city' => 'Tefé'],
        ['name' => 'Estádio Iquitos', 'city' => 'Iquitos'],
        ['name' => 'Arena Marañón', 'city' => 'Yurimaguas'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stadium = fake()->randomElement(self::STADIUMS);

        return [
            'team_id' => Team::factory(),
            'name' => $stadium['name'],
            'city' => $stadium['city'],
            'capacity' => fake()->numberBetween(8000, 60000),
        ];
    }
}
