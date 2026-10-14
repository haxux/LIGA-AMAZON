<?php

namespace App\Http\Controllers\Site;

use App\Enums\CupScope;
use App\Models\Cup;
use App\Models\Season;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Las copas de una temporada (Fase 15).
 *
 * Misma forma que Equipos: `?temporada` elige y lo que la lista no contenga cae
 * en la vigente. `?tipo=nacionales|internacionales` —lo que ofrece el menú de
 * Copas— separa las de casa de las internacionales; sin él salen todas.
 */
class CupsController extends SiteController
{
    public function __invoke(Request $request): View
    {
        $scope = match ($request->query('tipo')) {
            'nacionales' => CupScope::National,
            'internacionales' => CupScope::International,
            default => null,
        };

        $seasons = Season::query()
            ->whereHas('cups', fn ($query) => $query->when($scope, fn ($q) => $q->where('scope', $scope)))
            ->orderByDesc('start_date')->orderByDesc('id')->get();
        $season = $seasons->firstWhere('id', $request->integer('temporada'))
            ?? $seasons->firstWhere('id', $this->seasons->active()?->getKey())
            ?? $seasons->first();

        return view('site.cups.index', [
            'scope' => $scope,
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'cups' => $season === null
                ? collect()
                : Cup::query()
                    ->where('season_id', $season->getKey())
                    ->when($scope, fn ($query) => $query->where('scope', $scope))
                    ->withCount(['participants', 'rounds', 'groups'])
                    ->orderBy('name')
                    ->get(),
        ]);
    }
}
