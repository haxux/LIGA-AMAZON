<?php

namespace App\Http\Controllers\Site;

use App\Services\StandingsService;
use Illuminate\Contracts\View\View;

class StandingsController extends SiteController
{
    public function __invoke(StandingsService $standings): View
    {
        $season = $this->activeSeason();

        $divisions = $season->divisions()->has('teams')->orderBy('id')->get();

        // D6: a season with teams but zero qualifying divisions (reachable —
        // teams.division_id is permanently nullable) renders one unnamed
        // table via forSeason() instead of a blank page.
        if ($divisions->isEmpty()) {
            return view('site.standings', [
                'tables' => [
                    ['heading' => null, 'rows' => $standings->forSeason($season)],
                ],
            ]);
        }

        $tables = $divisions
            ->map(fn ($division) => [
                'heading' => $division->name,
                'rows' => $standings->forDivision($division),
            ])
            ->all();

        return view('site.standings', ['tables' => $tables]);
    }
}
