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
            // Un equipo sin división todavía no compite en nada: no se lista
            // (decisión del propietario), aunque siga existiendo en el panel.
            ->whereNotNull('division_id')
            ->with(['club', 'division'])
            ->get()
            ->sortBy(fn (Team $team) => $team->club?->name)
            ->values();

        $groups = $this->groupByDivision($teams, $season);

        // ?division elige una sola; una que no sea de esta temporada (o que no
        // exista) se ignora y se enseñan todas, como con la temporada.
        $division = collect($groups)->firstWhere('id', $request->integer('division'));

        return view('site.clubs.index', [
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'groups' => $division ? [$division] : $groups,
            'divisions' => collect($groups)->map(fn (array $group) => ['id' => $group['id'], 'name' => $group['heading']]),
            'selectedDivision' => $division['id'] ?? null,
        ]);
    }

    /**
     * Una sección por división, en el orden del panel.
     *
     * @param  Collection<int, Team>  $teams
     * @return array<int, array{id: int, heading: string|null, teams: Collection<int, Team>}>
     */
    private function groupByDivision(Collection $teams, Season $season): array
    {
        return $season->divisions()
            ->orderBy('id')
            ->get()
            ->map(fn (Division $division) => [
                'id' => $division->getKey(),
                'heading' => $division->name,
                'teams' => $teams->where('division_id', $division->getKey())->values(),
            ])
            ->filter(fn (array $group) => $group['teams']->isNotEmpty())
            ->values()
            ->all();
    }
}
