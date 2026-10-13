<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Lo que la portada cuenta de un vistazo: por dónde va la temporada, qué se
 * acaba de jugar, quién está en racha y las cifras que la gente comenta.
 *
 * Todo se deriva en vivo, como el resto del proyecto (ARQUITECTURA.md §3). Lo
 * que sí hace falta es NO pedir lo mismo varias veces: la portada es la página
 * más visitada del sitio y tres de sus bloques se alimentan del mismo conjunto
 * de partidos jugados, así que se pide UNA vez y se reparte. Una temporada
 * entera son unas decenas de partidos, de modo que ordenarlos y plegarlos en
 * PHP sale más barato que volver a la base por cada bloque.
 */
final class SeasonOverview
{
    /** @var array<int, Collection<int, Game>> */
    private array $played = [];

    /**
     * Los partidos jugados de la temporada, del más reciente al más antiguo.
     *
     * Incluye los de copa: cada tarjeta dice de qué competición es, así que
     * mezclarlos no engaña a nadie. Lo que NO puede mezclar es un bloque que
     * agregue en una sola cifra, y ésos piden `inLeague()` aparte.
     *
     * @return Collection<int, Game>
     */
    public function playedGames(Season $season): Collection
    {
        return $this->played[$season->getKey()] ??= Game::query()
            ->inSeason($season)
            ->played()
            ->with(['homeTeam.club', 'awayTeam.club', 'matchday'])
            ->get()
            ->sortByDesc(fn (Game $game) => $this->whenPlayed($game))
            ->values();
    }

    /**
     * Una clave ordenable por el momento en que se jugó: la hora del partido
     * si la tiene y, si no, la fecha de su jornada.
     *
     * Un partido sin ninguna de las dos no dice cuándo se jugó, así que se va
     * al final en vez de colarse arriba, y entre ésos decide el id, que es el
     * orden en que se metieron. Devuelve texto y no fechas porque hay que
     * comparar lo incomparable —datetime, date y nada— en un solo criterio.
     */
    private function whenPlayed(Game $game): string
    {
        $when = $game->kickoff_at ?? $game->matchday?->date;

        return ($when?->format('YmdHis') ?? '00000000000000')
            .str_pad((string) $game->getKey(), 12, '0', STR_PAD_LEFT);
    }

    /**
     * Los siguientes partidos de liga POR JUGAR, en orden de calendario.
     *
     * Por jugar de verdad: sin marcador y sin anular. La portada enseñaba «la
     * próxima jornada» entera y en una jornada a medias salían ahí los que ya
     * se habían jugado, con su resultado puesto, justo al lado del bloque de
     * últimos resultados que los repetía (lo vio el propietario el
     * 2026-10-13).
     *
     * Por partido y no por jornada: una jornada se juega a trozos, y un
     * aplazado de la 9 sigue estando por jugar cuando va la 10. Preguntando
     * por la jornada «de ahora» ese aplazado no aparecía en ninguna parte.
     *
     * @return Collection<int, Game>
     */
    public function upcomingGames(Season $season, int $limit = 6): Collection
    {
        return Game::query()
            ->inSeason($season)
            ->inLeague()
            ->whereNull('home_score')
            ->whereNull('away_score')
            ->whereNull('voided_at')
            ->with(['matchday.division', 'homeTeam.club', 'awayTeam.club'])
            ->get()
            // Clave compuesta y no un array de closures: `sortBy` con un array
            // las trata como COMPARADORES de dos argumentos, no como
            // extractores de clave, y el orden saldría al azar.
            ->sortBy(fn (Game $game) => sprintf(
                '%05d%012d',
                $game->matchday?->number ?? 99999,
                $game->getKey(),
            ))
            ->take($limit)
            ->values();
    }

    /**
     * Cuánto se ha jugado ya de la liga, para la barra de la portada.
     *
     * Sólo partidos, y no jornadas: el número de jornada lo pone el bloque de
     * la próxima jornada, que lo saca de MatchdayResolver. Calcularlo también
     * aquí daría dos cifras de la misma cosa en la misma pantalla, y el día que
     * discreparan nadie sabría cuál creer.
     *
     * @return array{played: int, games: int}
     */
    public function progress(Season $season): array
    {
        return [
            'played' => Game::query()->inSeason($season)->inLeague()->played()->count(),
            'games' => Game::query()->inSeason($season)->inLeague()->count(),
        ];
    }

    /**
     * Las cifras curiosas de la liga.
     *
     * Casi todas salen de las tablas que la portada ya tiene pintadas, sin
     * volver a la base: la suma de los goles a favor de todos los equipos ES el
     * total de goles, y el que más tiene es el más goleador. Sólo la mayor
     * goleada necesita mirar los partidos, y ésos también están ya pedidos.
     *
     * @param  array<int, array{heading: ?string, rows: Collection, zones: Collection}>  $tables
     * @return array{goals: int, average: ?float, biggest: ?Game, topScoring: ?Team, topScoringGoals: int}
     */
    public function highlights(Season $season, array $tables): array
    {
        $rows = collect($tables)->flatMap(fn (array $table) => $table['rows']->all());

        $goals = (int) $rows->sum('goals_for');
        // Cada partido lo cuentan sus dos equipos, de ahí la mitad.
        $games = (int) $rows->sum('played') / 2;

        $best = $rows->sortByDesc('goals_for')->first();

        $biggest = $this->playedGames($season)
            ->filter(fn (Game $game) => $game->matchday_id !== null)
            ->sortByDesc(fn (Game $game) => abs($game->home_score - $game->away_score))
            ->first();

        return [
            'goals' => $goals,
            'average' => $games > 0 ? round($goals / $games, 2) : null,
            // Un empate no es una goleada: sin diferencia, no hay dato.
            'biggest' => $biggest && $biggest->home_score !== $biggest->away_score ? $biggest : null,
            'topScoring' => ($best?->goals_for ?? 0) > 0 ? $best->team : null,
            'topScoringGoals' => (int) ($best->goals_for ?? 0),
        ];
    }

    /**
     * Los equipos en mejor racha: sus últimos partidos de LIGA plegados en
     * puntos, con la tira de resultados para enseñarla.
     *
     * De liga porque la racha se compara entre equipos, y uno que jugó copa
     * esta semana y otro que no tendrían rachas de distinta materia.
     *
     * Un equipo con menos partidos de los que se piden entra con los que tenga:
     * al principio de la temporada nadie llega a cinco, y esperar a que lleguen
     * dejaría el bloque vacío justo cuando más se mira.
     *
     * @return Collection<int, array{team: Team, points: int, results: array<int, string>}>
     */
    public function form(Season $season, int $howMany = 5, int $limit = 3): Collection
    {
        $league = $this->playedGames($season)->filter(fn (Game $game) => $game->matchday_id !== null);

        $perTeam = [];

        // Ya vienen del más reciente al más antiguo, así que los primeros que
        // caen de cada equipo son justo sus últimos.
        foreach ($league as $game) {
            foreach ([[$game->homeTeam, $game->home_score, $game->away_score], [$game->awayTeam, $game->away_score, $game->home_score]] as [$team, $own, $other]) {
                if ($team === null || count($perTeam[$team->getKey()]['results'] ?? []) >= $howMany) {
                    continue;
                }

                $perTeam[$team->getKey()] ??= ['team' => $team, 'points' => 0, 'results' => []];
                $perTeam[$team->getKey()]['points'] += $own > $other ? 3 : ($own === $other ? 1 : 0);
                $perTeam[$team->getKey()]['results'][] = $own > $other ? 'V' : ($own === $other ? 'E' : 'D');
            }
        }

        // A igualdad de puntos gana quien los sacó en menos partidos: tres
        // victorias de tres es mejor racha que tres victorias y dos derrotas.
        return collect($perTeam)
            ->sortByDesc(fn (array $row) => [$row['points'], -count($row['results'])])
            ->values()
            ->take($limit);
    }
}
