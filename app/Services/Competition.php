<?php

namespace App\Services;

use App\Models\Cup;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Qué competición se está mirando: todo junto, sólo la liga, o una copa
 * concreta. Es el filtro que la Fase 15 añade a las estadísticas, porque un
 * goleador de la liga y un goleador de la copa no son la misma cifra (decisión
 * del propietario: «separados por competición»).
 *
 * No hay una opción «todas las copas»: sería un cajón sin lector. Quien mira
 * una copa mira UNA, y para la suma ya está «Todo».
 *
 * La clave viaja por la URL (`?competicion=`) y por eso es texto y no un id
 * suelto: `todo`, `liga` o `copa:7`. Una clave inventada cae en «Todo», igual
 * que una pestaña inventada cae en General — las URLs de un sitio público las
 * escribe cualquiera.
 */
final class Competition
{
    public const ALL = 'todo';

    public const LEAGUE = 'liga';

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?int $cupId = null,
    ) {}

    public static function all(): self
    {
        return new self(self::ALL, 'Todo');
    }

    public static function league(): self
    {
        return new self(self::LEAGUE, 'Liga');
    }

    public static function cup(Cup $cup): self
    {
        return new self('copa:'.$cup->getKey(), $cup->name, $cup->getKey());
    }

    public function isAll(): bool
    {
        return $this->key === self::ALL;
    }

    public function isLeague(): bool
    {
        return $this->key === self::LEAGUE;
    }

    /**
     * Las que se pueden elegir en una temporada: todo, la liga y una entrada
     * por copa. Sin copas quedan dos, y entonces el selector no se pinta.
     *
     * @return Collection<int, self>
     */
    public static function forSeason(?Season $season): Collection
    {
        $cups = $season === null
            ? collect()
            : Cup::query()->where('season_id', $season->getKey())->orderBy('name')->get();

        return collect([self::all(), self::league()])
            ->concat($cups->map(fn (Cup $cup) => self::cup($cup)))
            ->values();
    }

    /**
     * Las de un equipo: sólo las copas que DISPUTA, con el mismo criterio que
     * la ficha de un club usa para las temporadas —ofrecer una copa en la que
     * no jugó es ofrecer una pantalla de ceros.
     *
     * @return Collection<int, self>
     */
    public static function forTeam(Team $team): Collection
    {
        $cups = Cup::query()
            ->whereHas('participants', fn (Builder $query) => $query->where('team_id', $team->getKey()))
            ->orderBy('name')
            ->get();

        return collect([self::all(), self::league()])
            ->concat($cups->map(fn (Cup $cup) => self::cup($cup)))
            ->values();
    }

    /**
     * La elegida de entre unas opciones dadas, o «Todo» si la clave no está
     * entre ellas.
     *
     * @param  Collection<int, self>  $options
     */
    public static function resolve(Collection $options, ?string $key): self
    {
        return $options->firstWhere('key', (string) $key) ?? self::all();
    }

    /**
     * Para `x-site.filter-select`.
     *
     * @param  Collection<int, self>  $options
     * @return array<string, string>
     */
    public static function asSelectOptions(Collection $options): array
    {
        return $options->mapWithKeys(fn (self $competition) => [$competition->key => $competition->label])->all();
    }

    /**
     * Acota una consulta de partidos a esta competición.
     *
     * @param  Builder<Game>  $games
     * @return Builder<Game>
     */
    public function applyTo(Builder $games): Builder
    {
        return match (true) {
            $this->cupId !== null => $games->inCup($this->cupId),
            $this->key === self::LEAGUE => $games->inLeague(),
            default => $games,
        };
    }

    /**
     * Lo mismo, pero sobre un partido ya cargado: las cifras de un club salen
     * de una colección en memoria y no de una consulta.
     */
    public function matches(Game $game): bool
    {
        return match (true) {
            $this->cupId !== null => (int) ($game->cup()?->getKey() ?? 0) === $this->cupId,
            $this->key === self::LEAGUE => $game->matchday_id !== null,
            default => true,
        };
    }
}
