<?php

namespace App\Http\Controllers\Site;

use App\Models\Division;
use App\Models\Matchday;
use App\Models\Season;
use App\Services\MatchdayResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FixturesController extends SiteController
{
    /**
     * Sentinel for the two "no narrowing" filter values, in the query string
     * and in the <select> markup alike.
     */
    private const ALL = 'todas';

    public function __invoke(Request $request, MatchdayResolver $resolver): View
    {
        $seasons = Season::query()->orderByDesc('start_date')->orderByDesc('id')->get();
        $season = $seasons->firstWhere('id', $request->integer('temporada')) ?? $this->activeSeason();

        $divisions = $season->divisions()->orderBy('id')->get();
        $division = $divisions->firstWhere('id', $request->integer('division'));

        $matchdays = $season->matchdays()
            ->when($division, fn ($query) => $query->where('division_id', $division->getKey()))
            ->with(['division', 'games.homeTeam', 'games.awayTeam'])
            ->orderBy('number')
            ->get();

        $numbers = $matchdays->pluck('number')->unique()->sort()->values();
        $number = $this->resolveNumber($request, $numbers, $matchdays, $resolver);

        return view('site.fixtures', [
            'seasons' => $seasons,
            'divisions' => $divisions,
            'numbers' => $numbers,
            'selectedSeason' => $season,
            'selectedDivision' => $division,
            'selectedNumber' => $number,
            'all' => self::ALL,
            'groups' => $this->groupByDivision(
                $number === null ? $matchdays : $matchdays->where('number', $number),
                $division ? collect([$division]) : $divisions,
            ),
        ]);
    }

    /**
     * An explicit ?jornada wins when it exists in the current selection;
     * 'todas' means the whole calendar; anything else (absent, stale after a
     * season switch, made up) falls back to the matchday being played now.
     *
     * @param  Collection<int, int>  $numbers
     * @param  Collection<int, Matchday>  $matchdays
     */
    private function resolveNumber(Request $request, Collection $numbers, Collection $matchdays, MatchdayResolver $resolver): ?int
    {
        $requested = $request->query('jornada');

        if ($requested === self::ALL) {
            return null;
        }

        if (is_numeric($requested) && $numbers->contains((int) $requested)) {
            return (int) $requested;
        }

        return $resolver->current($matchdays)?->number;
    }

    /**
     * One section per division, in panel order, each holding the matchdays
     * left after filtering. Divisions with nothing to show are dropped, so a
     * jornada that only one division plays does not render an empty heading.
     *
     * @param  Collection<int, Matchday>  $matchdays
     * @param  Collection<int, Division>  $divisions
     * @return array<int, array{division: Division, matchdays: Collection<int, Matchday>}>
     */
    private function groupByDivision(Collection $matchdays, Collection $divisions): array
    {
        return $divisions
            ->map(fn (Division $division) => [
                'division' => $division,
                'matchdays' => $matchdays->where('division_id', $division->getKey())->values(),
            ])
            ->filter(fn (array $group) => $group['matchdays']->isNotEmpty())
            ->values()
            ->all();
    }
}
