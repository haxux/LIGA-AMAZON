<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * Nombres ya entregados en ESTE proceso. `fake()->unique()` no basta por dos
     * razones: no sabe de los clubes que un test creó con nombre puesto a mano
     * —y `clubs.name` es único, así que la colisión tumba la suite una de cada
     * pocas ejecuciones— y dentro de un `->count(N)->create()` todas las
     * instancias se construyen antes de que ninguna se guarde, así que mirar la
     * base tampoco alcanza.
     *
     * @var array<int, string>
     */
    private static array $handedOut = [];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = self::availableName();

        return [
            'name' => $name,
            'short_name' => TeamFactory::CLUBS[$name]['short'] ?? mb_strtoupper(mb_substr($name, 0, 3)),
            'crest_path' => null,
            'founded_year' => fake()->numberBetween(1900, 2015),
        ];
    }

    /**
     * Un nombre del repertorio que no esté cogido; agotado el repertorio, uno
     * numerado, que sigue siendo legible en un fallo de test.
     */
    private static function availableName(): string
    {
        $taken = array_merge(Club::query()->pluck('name')->all(), self::$handedOut);
        $free = array_values(array_diff(array_keys(TeamFactory::CLUBS), $taken));

        $name = $free !== []
            ? fake()->randomElement($free)
            : self::numberedName($taken);

        self::$handedOut[] = $name;

        return $name;
    }

    /**
     * @param  array<int, string>  $taken
     */
    private static function numberedName(array $taken): string
    {
        $index = count($taken) + 1;

        while (in_array("Club {$index}", $taken, true)) {
            $index++;
        }

        return "Club {$index}";
    }
}
