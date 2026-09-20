<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Player;
use App\Models\SquadMembership;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Pertenencias pendientes de crear, por instancia de jugador. Hace falta
     * un puente entre `afterMaking` —donde se retiran los atributos que ya no
     * son columnas de `players`— y `afterCreating`, que es el único momento en
     * que el jugador tiene id para colgarle la pertenencia.
     *
     * @var array<int, array{team_id: int|string, shirt_number: int|null}>
     */
    private static array $pendingMemberships = [];

    /**
     * Define the model's default state.
     *
     * `team_id` y `shirt_number` siguen aceptándose por comodidad —los usan una
     * veintena de tests— aunque desde la Fase 9 vivan en `squad_memberships`:
     * el club sale del equipo indicado y la pertenencia se crea después.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => fn (array $attributes) => self::clubFor($attributes),
            'name' => fake()->name(),
            'position' => fake()->randomElement(Player::POSITIONS),
            'birth_date' => fake()->dateTimeBetween('-38 years', '-16 years')->format('Y-m-d'),
        ];
    }

    /**
     * Los factories construyen el modelo dentro de `Model::unguarded()`, así que
     * `#[Fillable]` no filtra: los atributos que ya no son columnas llegarían al
     * INSERT. Se retiran aquí y se convierten en la pertenencia de ahí abajo.
     */
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Player $player): void {
                $attributes = $player->getAttributes();
                $teamId = $attributes['team_id'] ?? null;
                $shirtNumber = $attributes['shirt_number'] ?? null;

                unset($player->team_id, $player->shirt_number);

                if ($teamId !== null) {
                    self::$pendingMemberships[spl_object_id($player)] = [
                        'team_id' => $teamId,
                        'shirt_number' => $shirtNumber === null ? null : (int) $shirtNumber,
                    ];
                }
            })
            ->afterCreating(function (Player $player): void {
                $pending = self::$pendingMemberships[spl_object_id($player)] ?? null;

                if ($pending === null) {
                    return;
                }

                unset(self::$pendingMemberships[spl_object_id($player)]);

                SquadMembership::create([
                    'team_id' => $pending['team_id'],
                    'player_id' => $player->getKey(),
                    // El dorsal se calcula AQUÍ, ya persistido el jugador anterior,
                    // así que un ->count(18)->create() reparte 1..18 sin colisiones.
                    'shirt_number' => $pending['shirt_number'] ?? SquadMembershipFactory::nextShirtNumber($pending['team_id']),
                    'type' => SquadMembership::TYPE_OWNED,
                ]);
            });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function clubFor(array $attributes): int
    {
        $teamId = $attributes['team_id'] ?? null;

        if ($teamId !== null) {
            return (int) Team::query()->whereKey($teamId)->value('club_id');
        }

        return Club::factory()->create()->getKey();
    }
}
