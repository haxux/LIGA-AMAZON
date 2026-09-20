<?php

namespace Database\Factories;

use App\Models\Club;
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
    /**
     * Atributos que describen al CLUB y que el llamante puede seguir pasando a
     * este factory por comodidad, aunque vivan en otra tabla.
     *
     * @var array<int, string>
     */
    private const CLUB_ATTRIBUTES = ['name', 'short_name', 'crest_path', 'founded_year'];

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
     * `club_id` se resuelve leyendo los atributos que el llamante haya pasado:
     * `Team::factory()->create(['name' => 'Manaos FC'])` sigue funcionando y crea
     * (o reutiliza) ese club, aunque `teams` ya no tenga columna `name`. La
     * asignación masiva descarta esas claves al construir el modelo, porque
     * desde la Fase 9 no están en `#[Fillable]` de Team.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'division_id' => null,
            'club_id' => fn (array $attributes) => self::clubFor($attributes),
        ];
    }

    /**
     * Los factories construyen el modelo dentro de `Model::unguarded()`
     * (Factory::makeInstance), así que `#[Fillable]` no filtra nada y los
     * atributos de identidad llegarían al INSERT de `teams`, que ya no tiene
     * esas columnas. Se retiran aquí, después de que `definition()` los haya
     * usado para resolver el club.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Team $team): void {
            foreach (self::CLUB_ATTRIBUTES as $attribute) {
                unset($team->{$attribute});
            }
        });
    }

    /**
     * Reutiliza el club si ya existe con ese nombre — dos temporadas del mismo
     * club son dos filas de `teams` y UN club, que es justo el invariante que
     * la Fase 9 introduce.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function clubFor(array $attributes): int
    {
        $name = $attributes['name'] ?? null;

        if ($name !== null) {
            $club = Club::query()->where('name', $name)->first();

            if ($club !== null) {
                return $club->getKey();
            }
        }

        $overrides = array_filter([
            'name' => $name,
            'short_name' => $attributes['short_name'] ?? ($name !== null ? (self::CLUBS[$name]['short'] ?? mb_strtoupper(mb_substr($name, 0, 3))) : null),
            'crest_path' => $attributes['crest_path'] ?? null,
            'founded_year' => $attributes['founded_year'] ?? null,
        ], fn ($value) => $value !== null);

        return Club::factory()->create($overrides)->getKey();
    }
}
