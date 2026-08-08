<?php

namespace App\Http\Controllers\Site;

use Illuminate\Contracts\View\View;

class FixturesController extends SiteController
{
    public function __invoke(): View
    {
        $season = $this->activeSeason();

        $matchdays = $season->matchdays()
            ->with(['games.homeTeam', 'games.awayTeam'])
            ->orderBy('number')
            ->get();

        return view('site.fixtures', ['matchdays' => $matchdays]);
    }
}
