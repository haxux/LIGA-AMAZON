<?php

namespace App\Http\Controllers\Site;

use App\Models\GameEvent;
use App\Services\Competition;
use App\Services\GoalscorersService;
use App\Services\StandingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Las clasificaciones de las temporadas ya cerradas (decisión del propietario:
 * Clasificación enseña la de ahora y el pasado se mira aquí).
 *
 * Sólo ofrece las temporadas que de verdad se jugaron —ver
 * SeasonResolver::archived()—, así que mientras no haya ninguna cerrada la
 * página existe y lo dice, en vez de enseñar un desplegable de temporadas
 * vacías.
 */
class HistoryController extends SiteController
{
    public function __invoke(Request $request, StandingsService $standings, GoalscorersService $goalscorers): View
    {
        $seasons = $this->seasons->archived();

        if ($seasons->isEmpty()) {
            return view('site.history', [
                'seasons' => $seasons,
                'selectedSeason' => null,
                'tables' => [],
                'champions' => [],
                'scorers' => collect(),
            ]);
        }

        // Mismo contrato que el resto del sitio: `?temporada` elige, y lo que
        // la lista no contenga —ausente, inventada, o la vigente, que aquí no
        // está— cae en la más reciente del archivo.
        $season = $seasons->firstWhere('id', $request->integer('temporada')) ?? $seasons->first();

        $tables = $standings->tablesFor($season);

        return view('site.history', [
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'tables' => $tables,
            'champions' => $this->champions($tables),
            // De la liga y no de todo: en esta página el campeón sale de la
            // tabla de liga, y dos cifras juntas que vengan de competiciones
            // distintas se leen como si fueran del mismo torneo.
            'scorers' => $goalscorers->leaders($season, GameEvent::TYPE_GOAL, Competition::league()),
        ]);
    }

    /**
     * El campeón de cada división: la primera fila de su tabla, que ya viene
     * ordenada por puntos, diferencia y goles a favor.
     *
     * Una división sin un solo partido jugado NO tiene campeón, aunque tenga
     * equipos: su tabla está a ceros y el primero lo sería por orden de id.
     *
     * @param  array<int, array{heading: ?string, rows: Collection, zones: Collection}>  $tables
     * @return array<int, array{division: ?string, row: mixed}>
     */
    private function champions(array $tables): array
    {
        return collect($tables)
            ->filter(fn (array $table) => ($table['rows']->first()?->played ?? 0) > 0)
            ->map(fn (array $table) => [
                'division' => $table['heading'],
                'row' => $table['rows']->first(),
            ])
            ->values()
            ->all();
    }
}
