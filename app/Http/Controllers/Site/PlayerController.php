<?php

namespace App\Http\Controllers\Site;

use App\Models\Player;
use App\Models\Team;
use App\Services\Competition;
use App\Services\PlayerStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * La ficha de un jugador: quién es y lo que lleva hecho.
 *
 * Las temporadas que se ofrecen son las que jugó, igual que en la ficha de un
 * club: ofrecer las demás daría una página vacía por elegir bien. Con las
 * competiciones vale lo mismo desde la Fase 15 — sólo las que disputó su equipo
 * de esa temporada.
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

        $competitions = $team instanceof Team ? Competition::forTeam($team) : Competition::forSeason($season);
        $competition = Competition::resolve($competitions, $request->string('competicion')->toString());

        return view('site.players.show', [
            'player' => $player,
            'seasons' => $seasons,
            'selectedSeason' => $season,
            'club' => $selected['club'] ?? $player->club,
            'shirtNumber' => $selected['shirt_number'] ?? null,
            // No se toman los totales ya calculados por `bySeason()`: aquéllos
            // son los de la temporada entera, y aquí manda la competición
            // elegida. La tabla de abajo sí usa los de la temporada entera,
            // porque es el resumen de una carrera.
            'seasonTotals' => $stats->totals($player, $season, $competition),
            'careerTotals' => $stats->totals($player),
            'events' => $stats->events($player, $season, 20, $competition),
            'competition' => $competition,
            'competitionOptions' => Competition::asSelectOptions($competitions),
        ]);
    }
}
