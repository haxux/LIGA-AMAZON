<?php

namespace App\Http\Controllers\Site;

use App\Models\Division;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * La sección Equipos (Fase 11). Públicamente se llama así, pero lo que se
 * muestra es el CLUB: la identidad que sobrevive a las temporadas. Cada fila de
 * `teams` es su participación en una de ellas, y es la que aporta división,
 * plantilla, calendario y clasificación de ese año.
 */
class ClubsController extends SiteController
{
    public function __invoke(Request $request): View
    {
        $seasons = Season::query()->orderByDesc('start_date')->orderByDesc('id')->get();

        // Mismo contrato que Clasificación y Partidos: ?temporada elige, y lo
        // que la lista no contenga —ausente, borrado, inventado— cae en la
        // temporada vigente en lugar de dar error.
        $season = $seasons->firstWhere('id', $request->integer('temporada')) ?? $this->activeSeason();

        $teams = Team::query()
            ->where('season_id', $season->getKey())
            ->with(['club', 'division'])
            ->get()
            ->sortBy(fn (Team $team) => $team->club?->name)
            ->values();

        return view('site.clubs.index', [
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'groups' => $this->groupByDivision($teams, $season),
        ]);
    }

    /**
     * Una sección por división, en el orden del panel, y una última para los
     * equipos sin división. `teams.division_id` es nullable desde la Fase 2, así
     * que un club recién inscrito puede no tenerla todavía: dejarlo fuera del
     * listado sería esconderlo justo cuando alguien lo busca.
     *
     * @param  Collection<int, Team>  $teams
     * @return array<int, array{heading: string|null, teams: Collection<int, Team>}>
     */
    private function groupByDivision(Collection $teams, Season $season): array
    {
        $groups = $season->divisions()
            ->orderBy('id')
            ->get()
            ->map(fn (Division $division) => [
                'heading' => $division->name,
                'teams' => $teams->where('division_id', $division->getKey())->values(),
            ]);

        $orphans = $teams->whereNull('division_id')->values();

        if ($orphans->isNotEmpty()) {
            $groups->push(['heading' => 'Sin división', 'teams' => $orphans]);
        }

        return $groups
            ->filter(fn (array $group) => $group['teams']->isNotEmpty())
            ->values()
            ->all();
    }
}
