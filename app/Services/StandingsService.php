<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Derives a season's league table live from `Game` rows. Nothing here is
 * persisted or cached — see ARQUITECTURA.md §3 ("se deriva, no se guarda
 * como fuente de verdad") and design D2/D3.
 */
final class StandingsService
{
    /**
     * El club va precargado en los equipos porque `Team::$crest_path` y
     * `$short_name` son accesores que pasan POR él: sin esto, cada escudo de
     * cada fila de cada tabla es una consulta suelta, y una tabla es justo el
     * sitio donde hay una fila por equipo.
     */
    /**
     * @return Collection<int, StandingRow> ordered points desc, goal_difference desc, goals_for desc
     */
    public function forSeason(Season $season): Collection
    {
        return $this->buildTable(
            Team::query()->where('season_id', $season->getKey())->with('club')->orderBy('id')->get(),
            $season->getKey(),
        );
    }

    /**
     * @return Collection<int, StandingRow> ordered points desc, goal_difference desc, goals_for desc
     */
    public function forDivision(Division $division): Collection
    {
        return $this->buildTable(
            Team::query()->where('division_id', $division->getKey())->with('club')->orderBy('id')->get(),
            $division->season_id,
        );
    }

    /**
     * Shared fold, extracted from what used to be forSeason()'s own body
     * (design D3): both public methods query the same season-scoped game
     * set — only the roster (row universe) narrows for forDivision(). The
     * accumulate() isset() roster guard then does the rest for free: a
     * cross-division game credits only the in-division side, with no extra
     * filtering on the games query itself.
     *
     * @return Collection<int, StandingRow> ordered points desc, goal_difference desc, goals_for desc
     */
    /**
     * La misma tabla, pero a partir de un conjunto de partidos dado en vez de
     * los de una temporada entera. Es lo que necesita un grupo de copa, que es
     * una liga pequeña con sus propios partidos (Fase 15).
     *
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, Game>  $games
     * @return Collection<int, StandingRow> ordered points desc, goal_difference desc, goals_for desc
     */
    public function fromGames(Collection $teams, Collection $games): Collection
    {
        return $this->accumulateInto($teams, $games->filter(
            fn (Game $game) => $game->home_score !== null && $game->away_score !== null,
        ));
    }

    /**
     * Las tablas de una temporada tal como se pintan: una por división, con su
     * nombre y sus franjas de ascenso y descenso.
     *
     * Vive aquí y no en el controlador porque la piden DOS páginas —
     * Clasificación, con la temporada vigente, e Historia, con una cerrada—, y
     * una de las dos reglas es sutil: una temporada con equipos pero sin
     * ninguna división que los tenga (`teams.division_id` es nullable para
     * siempre, diseño D6) devuelve UNA tabla sin título en vez de una página en
     * blanco. Duplicada en dos sitios, esa regla se arregla en uno y se olvida
     * en el otro.
     *
     * Cada tabla lleva además su campeón, si ya lo es matemáticamente (ver
     * `clinchedChampion()`); si no, null.
     *
     * @return array<int, array{heading: ?string, rows: Collection<int, StandingRow>, zones: Collection<int, mixed>, champion: ?Team}>
     */
    public function tablesFor(Season $season): array
    {
        $divisions = $season->divisions()->has('teams')->with('standingZones')->orderBy('id')->get();
        $remaining = $this->remainingGames($season->getKey());

        if ($divisions->isEmpty()) {
            $rows = $this->forSeason($season);

            return [['heading' => null, 'rows' => $rows, 'zones' => collect(), 'champion' => $this->clinchedChampion($rows, $remaining)]];
        }

        // Los partidos de la temporada, UNA vez para todas las divisiones: son
        // los mismos para cada una —lo que cambia es qué equipos tienen fila—,
        // y pedirlos por división repetía la misma consulta.
        $games = $this->leagueGames($season->getKey());

        return $divisions
            ->map(function (Division $division) use ($season, $games, $remaining) {
                $rows = $this->buildTable(
                    Team::query()->where('division_id', $division->getKey())->with('club')->orderBy('id')->get(),
                    $season->getKey(),
                    $games,
                );

                return [
                    'heading' => $division->name,
                    'rows' => $rows,
                    // Las franjas de ascenso y descenso son de la división, así
                    // que la tabla sin división de arriba simplemente no tiene.
                    'zones' => $division->standingZones,
                    'champion' => $this->clinchedChampion($rows, $remaining),
                ];
            })
            ->all();
    }

    /**
     * El campeón, en cuanto ya no le puede alcanzar nadie: sus puntos superan
     * a lo máximo que cualquier otro puede sumar con lo que le queda —sus
     * puntos más tres por cada partido de liga sin jugar—.
     *
     * Estrictamente por encima: con un empate posible a puntos decidiría la
     * diferencia de goles, que todavía no se sabe. Con el calendario entero
     * jugado, en cambio, la tabla ya es la definitiva y el primero es campeón
     * aunque empate a puntos con el segundo.
     *
     * Cuenta con que el calendario está creado entero, que es como se monta
     * la liga: un partido que aún no existe no se cuenta entre los pendientes.
     *
     * @param  Collection<int, StandingRow>  $rows  ya ordenadas
     * @param  array<int, int>  $remaining  partidos pendientes por equipo
     */
    public function clinchedChampion(Collection $rows, array $remaining): ?Team
    {
        $leader = $rows->first();

        if ($leader === null || $leader->played === 0 || $rows->count() < 2) {
            return null;
        }

        $pending = fn (StandingRow $row) => $remaining[$row->team->getKey()] ?? 0;

        if ($rows->every(fn (StandingRow $row) => $pending($row) === 0)) {
            return $leader->team;
        }

        $chase = $rows->slice(1)->max(fn (StandingRow $row) => $row->points + 3 * $pending($row));

        return $leader->points > $chase ? $leader->team : null;
    }

    /**
     * Los partidos de liga que le quedan a cada equipo de la temporada: sin
     * marcador y sin anular (uno anulado cuenta como jugado, ver
     * `leagueGames()`).
     *
     * @return array<int, int>
     */
    private function remainingGames(int $seasonId): array
    {
        $remaining = [];

        $games = Game::query()
            ->whereNull('voided_at')
            ->where(fn (Builder $query) => $query->whereNull('home_score')->orWhereNull('away_score'))
            ->whereHas('matchday', fn (Builder $query) => $query->where('season_id', $seasonId))
            ->get(['home_team_id', 'away_team_id']);

        foreach ($games as $game) {
            foreach ([$game->home_team_id, $game->away_team_id] as $teamId) {
                $remaining[$teamId] = ($remaining[$teamId] ?? 0) + 1;
            }
        }

        return $remaining;
    }

    private function buildTable(Collection $teams, int $seasonId, ?Collection $games = null): Collection
    {
        $rows = $teams
            ->mapWithKeys(fn (Team $team) => [$team->getKey() => [
                'team' => $team,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
            ]])
            ->all();

        return $this->fold($rows, $games ?? $this->leagueGames($seasonId));
    }

    /**
     * @return Collection<int, Game>
     */
    private function leagueGames(int $seasonId): Collection
    {
        // Sólo los de liga: desde la Fase 15 un partido puede ser de copa, y
        // ésos no cuentan para la tabla de una división.
        //
        // Entran los que tienen marcador Y TAMBIÉN los anulados, tengan el que
        // tengan. Un anulado cuenta como jugado sin repartir nada, así que
        // filtrarlo por el marcador antes de plegar lo hacía desaparecer de la
        // tabla entera: a un equipo con tres anulados le salían ocho partidos
        // jugados en vez de diez, y sólo porque uno de los tres conservaba su
        // 0-0 y los otros dos lo habían perdido.
        return Game::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $scored) => $scored->whereNotNull('home_score')->whereNotNull('away_score'))
                ->orWhereNotNull('voided_at'))
            ->whereHas('matchday', fn (Builder $query) => $query->where('season_id', $seasonId))
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score', 'voided_at', 'voided_against']);
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, Game>  $games
     * @return Collection<int, StandingRow>
     */
    private function accumulateInto(Collection $teams, Collection $games): Collection
    {
        $rows = $teams
            ->mapWithKeys(fn (Team $team) => [$team->getKey() => [
                'team' => $team,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
            ]])
            ->all();

        return $this->fold($rows, $games);
    }

    /**
     * @param  array<int, array{team: Team, played: int, won: int, drawn: int, lost: int, goals_for: int, goals_against: int}>  $rows
     * @param  Collection<int, Game>  $games
     * @return Collection<int, StandingRow>
     */
    private function fold(array $rows, Collection $games): Collection
    {
        foreach ($games as $game) {
            $voided = $game->isVoidedWithoutWinner();

            // El cast sólo puede tocar a un anulado sin ganador —los demás
            // llegan con los dos marcadores—, y a ése no se le leen los goles:
            // `accumulate()` se sale en cuanto cuenta el partido como jugado.
            $this->accumulate($rows, $game->home_team_id, (int) $game->home_score, (int) $game->away_score, $voided);
            $this->accumulate($rows, $game->away_team_id, (int) $game->away_score, (int) $game->home_score, $voided);
        }

        return collect($rows)
            ->map(fn (array $row) => new StandingRow(...$row))
            ->sortBy([['points', 'desc'], ['goal_difference', 'desc'], ['goals_for', 'desc']])
            ->values();
    }

    /**
     * @param  array<int, array{team: Team, played: int, won: int, drawn: int, lost: int, goals_for: int, goals_against: int}>  $rows
     *
     * Un partido anulado (`Game::isVoided()`) cuenta para jugados y para nada
     * más: ni gana, ni empata, ni pierde, ni mueve goles ni puntos. Es la
     * regla de "pasó de fecha sin jugarse" — se cuenta como jugado, pero no
     * reparte nada a ninguno de los dos.
     */
    private function accumulate(array &$rows, int $teamId, int $for, int $against, bool $voided = false): void
    {
        if (! isset($rows[$teamId])) {
            return; // team is not on this season's roster — see D5
        }

        $rows[$teamId]['played']++;

        if ($voided) {
            return;
        }

        $rows[$teamId]['goals_for'] += $for;
        $rows[$teamId]['goals_against'] += $against;

        if ($for > $against) {
            $rows[$teamId]['won']++;
        } elseif ($for < $against) {
            $rows[$teamId]['lost']++;
        } else {
            $rows[$teamId]['drawn']++;
        }
    }
}
