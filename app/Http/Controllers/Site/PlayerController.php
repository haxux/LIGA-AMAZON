<?php

namespace App\Http\Controllers\Site;

use App\Models\Player;
use App\Models\Season;
use App\Services\PlayerStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

        return view('site.players.show', [
            'player' => $player,
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'club' => $selected['club'] ?? $player->club,
            'shirtNumber' => $selected['shirt_number'] ?? null,
            'seasonTotals' => $selected['totals'] ?? $this->emptyTotals($stats, $player, $season),
            'careerTotals' => $stats->totals($player),
            'events' => $stats->events($player, $season),
        ]);
    }

    /**
     * Un jugador sin ninguna pertenencia —fichado y aún sin inscribir, o salido
     * de la liga hace temporadas— no tiene fila de temporada de la que sacar
     * sus totales, pero sigue teniendo ficha.
     *
     * @return array<string, int>
     */
    private function emptyTotals(PlayerStatsService $stats, Player $player, ?Season $season): array
    {
        return $stats->totals($player, $season);
    }
}
