<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Season-wide (not division-scoped) goal/assist leaderboards, derived live
 * from `GameEvent` rows — mirrors StandingsService (Fase 4).
 */
final class GoalscorersService
{
    /**
     * @param  ?Team  $team  narrows the board to one club's season squad
     * @param  ?Competition  $competition  liga, una copa o todo (por omisión, todo)
     * @param  ?int  $divisionId  sólo partidos de liga de esa división
     * @param  ?int  $matchdayNumber  sólo partidos de liga de esa jornada
     * @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit
     */
    public function topScorers(Season $season, ?int $limit = 10, ?Team $team = null, ?Competition $competition = null, ?int $divisionId = null, ?int $matchdayNumber = null): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_GOAL, $limit, $team, $competition, $divisionId, $matchdayNumber);
    }

    /**
     * @param  ?Team  $team  narrows the board to one club's season squad
     * @param  ?Competition  $competition  liga, una copa o todo (por omisión, todo)
     * @param  ?int  $divisionId  sólo partidos de liga de esa división
     * @param  ?int  $matchdayNumber  sólo partidos de liga de esa jornada
     * @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit
     */
    public function topAssisters(Season $season, ?int $limit = 10, ?Team $team = null, ?Competition $competition = null, ?int $divisionId = null, ?int $matchdayNumber = null): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_ASSIST, $limit, $team, $competition, $divisionId, $matchdayNumber);
    }

    /**
     * @param  ?Team  $team  narrows the board to one club's season squad
     * @param  ?Competition  $competition  liga, una copa o todo (por omisión, todo)
     * @param  ?int  $divisionId  sólo partidos de liga de esa división
     * @param  ?int  $matchdayNumber  sólo partidos de liga de esa jornada
     * @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit
     */
    public function topCleanSheets(Season $season, ?int $limit = 10, ?Team $team = null, ?Competition $competition = null, ?int $divisionId = null, ?int $matchdayNumber = null): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_CLEAN_SHEET, $limit, $team, $competition, $divisionId, $matchdayNumber);
    }

    /**
     * Quienes más veces han sido MVP de un partido. No sale de `game_events`
     * —no es un evento— sino de la columna del propio partido, y por eso
     * acepta los mismos filtros que las demás listas.
     *
     * @return Collection<int, ScorerRow>
     */
    public function topMvps(Season $season, ?int $limit = 10, ?Competition $competition = null, ?int $divisionId = null, ?int $matchdayNumber = null): Collection
    {
        $competition ??= Competition::all();

        $counts = Game::query()
            ->whereNotNull('mvp_player_id')
            ->tap(fn (Builder $query) => $competition->applyTo($query->inSeason($season)))
            ->when($divisionId, fn (Builder $games) => $games->whereHas('matchday', fn (Builder $matchday) => $matchday->where('division_id', $divisionId)))
            ->when($matchdayNumber, fn (Builder $games) => $games->whereHas('matchday', fn (Builder $matchday) => $matchday->where('number', $matchdayNumber)))
            ->groupBy('mvp_player_id')
            ->pluck(DB::raw('COUNT(*)'), 'mvp_player_id');

        return $this->rowsFrom($season, $counts, $limit);
    }

    /**
     * Quienes más veces han sido jugador de la jornada (Week MVP). La jornada
     * sólo existe en la liga: con una copa elegida no hay nada que contar.
     *
     * @return Collection<int, ScorerRow>
     */
    public function topWeekMvps(Season $season, ?int $limit = 10, ?Competition $competition = null, ?int $divisionId = null, ?int $matchdayNumber = null): Collection
    {
        $competition ??= Competition::all();

        if (! ($competition->isAll() || $competition->isLeague())) {
            return collect();
        }

        $counts = Matchday::query()
            ->whereNotNull('week_mvp_player_id')
            ->where('season_id', $season->getKey())
            ->when($divisionId, fn (Builder $matchdays) => $matchdays->where('division_id', $divisionId))
            ->when($matchdayNumber, fn (Builder $matchdays) => $matchdays->where('number', $matchdayNumber))
            ->groupBy('week_mvp_player_id')
            ->pluck(DB::raw('COUNT(*)'), 'week_mvp_player_id');

        return $this->rowsFrom($season, $counts, $limit);
    }

    /**
     * De unos recuentos por jugador a filas ordenadas, con el club de la
     * temporada ya resuelto.
     *
     * @param  Collection<int|string, int|string>  $counts  jugador => total
     * @return Collection<int, ScorerRow>
     */
    private function rowsFrom(Season $season, Collection $counts, ?int $limit): Collection
    {
        $players = $this->hydrate($season, $counts->keys()->all());

        $rows = collect($players)
            ->map(fn (array $entry, int $id) => new ScorerRow($entry['player'], (int) $counts[$id], $entry['club']))
            ->sortBy([['count', 'desc']])
            ->values();

        return $limit === null ? $rows : $rows->take($limit);
    }

    /**
     * Las cabeceras de VARIAS listas a la vez, en una sola pasada.
     *
     * Existe por la portada, que pide tres —goleadores, asistentes y porterías
     * a cero— y las pedía con tres llamadas a `leaders()`. Cada una repetía su
     * propia carga de jugadores, pertenencias y clubes, y como el conjunto de
     * jugadores es casi el mismo, la misma consulta de clubes salía siete veces
     * en una sola petición. Medido: dieciocho consultas para tres cifras.
     *
     * Aquí los recuentos de los tres tipos salen de UNA consulta agrupada por
     * tipo y jugador, y los jugadores se hidratan una vez para los tres. El
     * criterio de empate es el de `leaders()`: salen todos los que igualen la
     * cabeza, y hay un test que compara las dos salidas para que no se separen.
     *
     * @param  array<int, string>  $types  constantes TYPE_ de GameEvent
     * @return array<string, Collection<int, ScorerRow>> indexado por tipo
     */
    public function leaderboards(Season $season, array $types, ?Competition $competition = null, int $cap = 5): array
    {
        $competition ??= Competition::all();

        $counts = GameEvent::query()
            ->whereIn('type', $types)
            ->whereHas('game', fn (Builder $query) => $competition->applyTo($query->inSeason($season)))
            ->groupBy('type', 'player_id')
            ->selectRaw('type, player_id, COUNT(*) as total')
            ->toBase()
            ->get();

        $players = $this->hydrate($season, $counts->pluck('player_id')->unique()->all());

        $boards = [];

        foreach ($types as $type) {
            $rows = $counts
                ->where('type', $type)
                ->map(fn (object $row) => isset($players[$row->player_id])
                    ? new ScorerRow($players[$row->player_id]['player'], (int) $row->total, $players[$row->player_id]['club'])
                    : null)
                ->filter()
                ->sortByDesc('count')
                ->values()
                ->take($cap);

            $best = $rows->first()?->count;

            $boards[$type] = $best === null
                ? collect()
                : $rows->filter(fn (ScorerRow $row) => $row->count === $best)->values();
        }

        return $boards;
    }

    /**
     * Los jugadores de unos ids, con el nombre corto del club en el que
     * jugaron esa temporada ya resuelto. Igual que en `leaderboard()`: la
     * pertenencia dice dónde jugó de verdad —una cesión cambia de club— y el
     * club propietario queda de respaldo.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{player: Player, club: ?string}>
     */
    private function hydrate(Season $season, array $ids): array
    {
        return Player::query()
            ->whereIn('id', $ids)
            ->with([
                'club',
                'memberships' => fn ($query) => $query->whereHas(
                    'team',
                    fn (Builder $teamQuery) => $teamQuery->where('season_id', $season->getKey()),
                )->with('team.club'),
            ])
            ->get()
            ->mapWithKeys(fn (Player $player) => [$player->getKey() => [
                'player' => $player,
                'club' => $player->memberships->first()?->team?->club?->short_name ?? $player->club?->short_name,
            ]])
            ->all();
    }

    /**
     * Quienes van EN CABEZA de una lista, con los empatados incluidos.
     *
     * Devolver uno solo sería inventarse un desempate que la liga no tiene: dos
     * jugadores con doce goles son los dos máximos goleadores. El tope existe
     * para que un empate absurdo —media liga a un gol en la primera jornada— no
     * desborde la pantalla que lo pinte.
     *
     * @param  string  $type  una de las constantes TYPE_ de GameEvent
     * @return Collection<int, ScorerRow>
     */
    public function leaders(Season $season, string $type, ?Competition $competition = null, int $cap = 5): Collection
    {
        $board = $this->leaderboard($season, $type, $cap, null, $competition);
        $best = $board->first()?->count;

        return $best === null
            ? collect()
            : $board->filter(fn (ScorerRow $row) => $row->count === $best)->values();
    }

    /**
     * El filtro por equipo es un parámetro y no un servicio aparte (design
     * D12): la ficha de un club necesita el mismo cómputo, acotado a quienes
     * estuvieron en su plantilla esa temporada. La pertenencia es el puente,
     * porque los eventos cuelgan del jugador y un cedido jugó en dos clubes.
     *
     * La competición es otro parámetro por lo mismo, desde la Fase 15: un
     * goleador de la liga y uno de la copa son dos cifras distintas, y sumarlas
     * sin decirlo es lo que el propietario pidió no hacer.
     *
     * @return Collection<int, ScorerRow>
     */
    private function leaderboard(Season $season, string $type, ?int $limit, ?Team $team = null, ?Competition $competition = null, ?int $divisionId = null, ?int $matchdayNumber = null): Collection
    {
        $competition ??= Competition::all();

        $counts = GameEvent::query()
            ->where('type', $type)
            // Por el partido y no por su jornada: uno de copa no tiene jornada,
            // y preguntando por ahí la copa entera se quedaba fuera.
            ->whereHas('game', fn (Builder $query) => $competition->applyTo($query->inSeason($season))
                // División y jornada son cosas de la liga: un partido de copa no
                // cuelga de ninguna, así que al elegirlas la copa queda fuera.
                ->when($divisionId, fn (Builder $games) => $games->whereHas('matchday', fn (Builder $matchday) => $matchday->where('division_id', $divisionId)))
                ->when($matchdayNumber, fn (Builder $games) => $games->whereHas('matchday', fn (Builder $matchday) => $matchday->where('number', $matchdayNumber))))
            ->when($team, fn (Builder $query, Team $team) => $query->whereHas(
                'player.memberships',
                fn (Builder $memberships) => $memberships->where('team_id', $team->getKey()),
            ))
            ->groupBy('player_id')
            ->pluck(DB::raw('COUNT(*)'), 'player_id');

        $rows = Player::query()
            ->whereIn('id', $counts->keys())
            ->with([
                'club',
                // La pertenencia de esta temporada dice dónde jugó realmente,
                // cesiones incluidas; el club propietario queda de respaldo.
                'memberships' => fn ($query) => $query->whereHas(
                    'team',
                    fn (Builder $teamQuery) => $teamQuery->where('season_id', $season->getKey()),
                )->with('team.club'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Player $player) => new ScorerRow(
                $player,
                (int) $counts[$player->getKey()],
                $player->memberships->first()?->team?->club?->short_name ?? $player->club?->short_name,
            ))
            ->sortBy([['count', 'desc']])
            ->values();

        return $limit === null ? $rows : $rows->take($limit);
    }
}
