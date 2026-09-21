<?php

namespace App\Http\Controllers\Site;

use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Services\Competition;
use App\Services\PlayerStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * La ficha de un jugador: quién es y lo que lleva hecho.
 *
 * Las temporadas que se ofrecen son las que jugó, igual que en la ficha de un
 * club: ofrecer las demás daría una página vacía por elegir bien.
 */
class PlayerController extends SiteController
{
    public function __invoke(Request $request, PlayerStatsService $stats, Player $player): View
    {
        $player->load('club');

        $seasons = $stats->bySeason($player);
        $selected = $seasons->firstWhere(fn (array $row) => $row['season']->getKey() === $request->integer('temporada'))
            ?? $seasons->firstWhere(fn (array $row) => $row['season']->getKey() === $this->seasons->active()?->getKey())
            ?? $seasons->first();

        $season = $selected['season'] ?? null;
        $team = $selected['team'] ?? null;

        return view('site.players.show', [
            'player' => $player,
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'club' => $selected['club'] ?? $player->club,
            'shirtNumber' => $selected['shirt_number'] ?? null,
            'statBlocks' => $this->statBlocks($stats, $player, $team, $season),
            'careerTotals' => $stats->totals($player),
            'events' => $stats->events($player, $season),
        ]);
    }

    /**
     * Lo que hizo en la temporada elegida, separado por competición desde la
     * Fase 15 (decisión del propietario): primero «General», que es la
     * temporada entera, y después una tanda por cada competición en la que está
     * su equipo — la liga y cada copa.
     *
     * Las competiciones son las del EQUIPO y no las de la temporada: enseñarle
     * a un jugador una copa que su club no juega es enseñarle una fila de ceros.
     * Sin copas no hay nada que separar, y la ficha queda como estaba.
     *
     * @return Collection<int, array{competition: Competition, title: ?string, totals: array<string, int>}>
     */
    private function statBlocks(PlayerStatsService $stats, Player $player, ?Team $team, ?Season $season): Collection
    {
        $competitions = $team === null ? collect([Competition::all()]) : Competition::forTeam($team);

        if ($competitions->count() <= 2) {
            return collect([[
                'competition' => Competition::all(),
                'title' => null,
                'totals' => $stats->totals($player, $season),
            ]]);
        }

        return $competitions->map(fn (Competition $competition) => [
            'competition' => $competition,
            'title' => $competition->isAll() ? 'General' : $competition->label,
            'totals' => $stats->totals($player, $season, $competition),
        ]);
    }
}
