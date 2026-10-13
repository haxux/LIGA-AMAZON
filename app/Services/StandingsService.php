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
     * @return array<int, array{heading: ?string, rows: Collection<int, StandingRow>, zones: Collection<int, mixed>}>
     */
    public function tablesFor(Season $season): array
    {
        $divisions = $season->divisions()->has('teams')->with('standingZones')->orderBy('id')->get();

        if ($divisions->isEmpty()) {
            return [['heading' => null, 'rows' => $this->forSeason($season), 'zones' => collect()]];
        }

        return $divisions
            ->map(fn (Division $division) => [
                'heading' => $division->name,
                'rows' => $this->forDivision($division),
                // Las franjas de ascenso y descenso son de la división, así que
                // la tabla sin división de arriba simplemente no tiene.
                'zones' => $division->standingZones,
            ])
            ->all();
    }

    private function buildTable(Collection $teams, int $seasonId): Collection
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

        // Sólo los de liga: desde la Fase 15 un partido puede ser de copa, y
        // ésos no cuentan para la tabla de una división.
        $games = Game::query()
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->whereHas('matchday', fn (Builder $query) => $query->where('season_id', $seasonId))
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score', 'voided_at', 'voided_against']);

        return $this->fold($rows, $games);
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
            $this->accumulate($rows, $game->home_team_id, $game->home_score, $game->away_score, $voided);
            $this->accumulate($rows, $game->away_team_id, $game->away_score, $game->home_score, $voided);
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
