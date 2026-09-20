<?php

namespace Database\Factories;

use App\Models\Club;
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
     * `team_id` se sigue aceptando por comodidad —el estadio se pedía por
     * equipo hasta la Fase 9— y se traduce al club de ese equipo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stadium = fake()->randomElement(self::STADIUMS);

        return [
            'club_id' => fn (array $attributes) => isset($attributes['team_id'])
                ? (int) Team::query()->whereKey($attributes['team_id'])->value('club_id')
                : Club::factory()->create()->getKey(),
            'name' => $stadium['name'],
            'city' => $stadium['city'],
            'capacity' => fake()->numberBetween(8000, 60000),
        ];
    }

    /**
     * Mismo motivo que en TeamFactory y PlayerFactory: los factories construyen
     * el modelo sin protección de asignación masiva, así que un `team_id` que
     * ya no es columna llegaría al INSERT.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Stadium $stadium): void {
            unset($stadium->team_id);
        });
    }
}
