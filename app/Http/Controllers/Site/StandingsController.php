<?php

namespace App\Http\Controllers\Site;

use App\Models\Division;
use App\Models\Season;
use App\Services\StandingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StandingsController extends SiteController
{
    public function __invoke(Request $request, StandingsService $standings): View
    {
        $seasons = Season::query()->orderByDesc('start_date')->orderByDesc('id')->get();

        // Same contract as the partidos page: ?temporada picks a season, and
        // anything the list does not hold — absent, deleted, made up — falls
        // back to the active one rather than erroring.
        $season = $seasons->firstWhere('id', $request->integer('temporada')) ?? $this->activeSeason();

        $divisions = $season->divisions()->has('teams')->orderBy('id')->get();

        // D6: a season with teams but zero qualifying divisions (reachable —
        // teams.division_id is permanently nullable) renders one unnamed
        // table via forSeason() instead of a blank page.
        $tables = $divisions->isEmpty()
            ? [['heading' => null, 'rows' => $standings->forSeason($season)]]
            : $divisions
                ->map(fn (Division $division) => [
                    'heading' => $division->name,
                    'rows' => $standings->forDivision($division),
                ])
                ->all();

        return view('site.standings', [
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'tables' => $tables,
        ]);
    }
}
