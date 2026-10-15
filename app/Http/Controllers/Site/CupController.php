<?php

namespace App\Http\Controllers\Site;

use App\Models\Cup;
use App\Models\CupGroup;
use App\Models\CupRound;
use App\Models\CupTie;
use App\Models\Game;
use App\Services\CupService;
use App\Services\StandingRow;
use App\Services\TieResult;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * La página de una copa: sus fases preliminares, sus grupos si los tiene, y
 * su cuadro.
 *
 * Nada de lo que se enseña aquí está guardado: el global de cada eliminatoria y
 * la tabla de cada grupo se derivan de los partidos en el momento, como la
 * clasificación de una liga. Lo único guardado es la decisión de un empate —
 * quién pasó y por qué—, porque eso no se deduce de ningún marcador.
 */
class CupController extends SiteController
{
    public function __invoke(CupService $cups, Cup $cup): View
    {
        $cup->load(['season', 'standingZones', 'thirdsStandingZones', 'groups.participants.team.club']);

        [$preliminary, $rounds] = $this->rounds($cups, $cup)
            ->partition(fn (array $entry) => $entry['round']->isPreliminary());

        return view('site.cups.show', [
            'cup' => $cup,
            'preliminaryRounds' => $preliminary->values(),
            'groups' => $this->groups($cups, $cup),
            'bestThirds' => $cups->bestThirds($cup),
            'rounds' => $rounds->values(),
        ]);
    }

    /**
     * @return Collection<int, array{group: CupGroup, table: Collection<int, StandingRow>}>
     */
    private function groups(CupService $cups, Cup $cup): Collection
    {
        if (! $cup->has_group_stage) {
            return collect();
        }

        return $cup->groups->map(fn (CupGroup $group) => [
            'group' => $group,
            'table' => $cups->groupTable($group),
        ]);
    }

    /**
     * Las rondas, de la primera a la final, con cómo va cada cruce.
     *
     * @return Collection<int, array{round: CupRound, ties: Collection<int, array{tie: CupTie, result: TieResult, legs: Collection<int, array{label: string, game: Game, home: ?int, away: ?int}>}>}>
     */
    private function rounds(CupService $cups, Cup $cup): Collection
    {
        return $cup->rounds()
            ->with(['ties.homeTeam.club', 'ties.awayTeam.club', 'ties.winner.club', 'ties.games'])
            ->get()
            ->map(fn (CupRound $round) => [
                'round' => $round,
                'ties' => $round->ties->map(fn ($tie) => [
                    'tie' => $tie,
                    'result' => $cups->result($tie),
                    'legs' => $this->legs($tie),
                ]),
            ]);
    }

    /**
     * Cada partido del cruce con el marcador leído desde el local DEL CRUCE, no
     * desde el de ese partido: en la vuelta los lados se invierten, y al lado
     * del global tienen que leerse en el mismo orden que él. La ida es la que
     * juega en casa el local del cruce.
     *
     * @return Collection<int, array{label: string, game: Game, home: ?int, away: ?int}>
     */
    private function legs(CupTie $tie): Collection
    {
        $games = $tie->games
            ->sortBy(fn (Game $game) => [$game->home_team_id === $tie->home_team_id ? 0 : 1, $game->id])
            ->values();

        return $games->map(function (Game $game, int $index) use ($tie, $games) {
            $atHome = $game->home_team_id === $tie->home_team_id;

            return [
                'label' => $games->count() === 1 ? 'Partido' : ($index === 0 ? 'Ida' : 'Vuelta'),
                'game' => $game,
                'home' => $atHome ? $game->home_score : $game->away_score,
                'away' => $atHome ? $game->away_score : $game->home_score,
            ];
        });
    }
}
