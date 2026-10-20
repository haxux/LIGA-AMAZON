<?php

namespace App\Http\Controllers\Site;

use App\Models\Division;
use App\Models\Season;
use App\Models\Team;
use App\Services\SeasonOverview;
use App\Services\StandingRow;
use App\Services\StandingsService;
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
    public function __invoke(Request $request, StandingsService $standings, SeasonOverview $overview): View
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
            ->get();

        // La racha de los últimos cinco de liga de cada equipo, la misma de
        // «En forma» en la portada, pero de todos y no sólo de los tres mejores.
        $form = $overview->form($season, 5, PHP_INT_MAX)
            ->mapWithKeys(fn (array $row) => [$row['team']->getKey() => array_reverse($row['results'])]);

        $groups = $this->groupByDivision($teams, $season, $standings, $form);

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
     * Una sección por división, en el orden del panel, y dentro de cada una
     * los equipos por su puesto en la tabla (decisión del propietario): así la
     * lista ya cuenta cómo va la liga, y no sólo quién está.
     *
     * Cada fila lleva el equipo, su puesto, su línea de la tabla y su racha
     * (de la más antigua a la más reciente, como se lee).
     *
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, array<int, string>>  $form
     * @return array<int, array{id: int, heading: string|null, teams: Collection<int, array{team: Team, position: int, standing: ?StandingRow, form: array<int, string>}>}>
     */
    private function groupByDivision(Collection $teams, Season $season, StandingsService $standings, Collection $form): array
    {
        return $season->divisions()
            ->orderBy('id')
            ->get()
            ->map(function (Division $division) use ($teams, $standings, $form) {
                $listed = $teams->where('division_id', $division->getKey())->keyBy(fn (Team $team) => $team->getKey());
                $table = $standings->forDivision($division)
                    ->filter(fn (StandingRow $row) => $listed->has($row->team->getKey()))
                    ->values();

                return [
                    'id' => $division->getKey(),
                    'heading' => $division->name,
                    'teams' => $table->map(fn (StandingRow $row, int $index) => [
                        'team' => $listed[$row->team->getKey()],
                        'position' => $index + 1,
                        'standing' => $row,
                        'form' => $form[$row->team->getKey()] ?? [],
                    ]),
                ];
            })
            ->filter(fn (array $group) => $group['teams']->isNotEmpty())
            ->values()
            ->all();
    }
}
