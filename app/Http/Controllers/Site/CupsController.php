<?php

namespace App\Http\Controllers\Site;

use App\Models\Cup;
use App\Models\Season;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Las copas de una temporada (Fase 15).
 *
 * Misma forma que Equipos: `?temporada` elige y lo que la lista no contenga cae
 * en la vigente.
 */
class CupsController extends SiteController
{
    public function __invoke(Request $request): View
    {
        $seasons = Season::query()->has('cups')->orderByDesc('start_date')->orderByDesc('id')->get();
        $season = $seasons->firstWhere('id', $request->integer('temporada'))
            ?? $seasons->firstWhere('id', $this->seasons->active()?->getKey())
            ?? $seasons->first();

        return view('site.cups.index', [
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'cups' => $season === null
                ? collect()
                : Cup::query()->where('season_id', $season->getKey())->withCount('participants')->orderBy('name')->get(),
        ]);
    }
}
