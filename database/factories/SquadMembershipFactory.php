<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\SquadMembership;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<SquadMembership>
 */
class SquadMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'shirt_number' => fn (array $attributes) => self::nextShirtNumber($attributes['team_id']),
            'type' => SquadMembership::TYPE_OWNED,
        ];
    }

    /**
     * La consulta lleva la unicidad ENTRE llamadas sueltas a create(); el índice
     * de la secuencia la lleva DENTRO de un ->count(N)->create(), donde las N
     * instancias se construyen antes de persistir ninguna. Mismo patrón que
     * MatchdayFactory con el número de jornada.
     */
    public function configure(): static
    {
        return $this->sequence(function (Sequence $sequence) {
            // El offset se captura ahora: Sequence::__invoke() incrementa el
            // índice en cuanto esta función retorna, y el cierre de abajo se
            // evalúa después, al expandir los atributos.
            $offset = $sequence->index;

            return [
                'shirt_number' => fn (array $attributes) => self::nextShirtNumber($attributes['team_id'], $offset),
            ];
        });
    }

    /**
     * El dorsal es único dentro de la plantilla, así que se toma el primero
     * libre en lugar de uno al azar.
     */
    public static function nextShirtNumber(int|string $teamId, int $offset = 0): int
    {
        return (int) SquadMembership::where('team_id', $teamId)->max('shirt_number') + 1 + $offset;
    }
}
