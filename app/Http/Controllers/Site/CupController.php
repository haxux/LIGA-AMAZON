<?php

namespace App\Http\Controllers\Site;

use App\Models\Cup;
use App\Models\CupGroup;
use App\Models\CupRound;
use App\Models\CupTie;
use App\Services\CupService;
use App\Services\StandingRow;
use App\Services\TieResult;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * La página de una copa: sus grupos, si los tiene, y su cuadro.
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
        $cup->load(['season', 'groups.participants.team.club']);

        return view('site.cups.show', [
            'cup' => $cup,
            'groups' => $this->groups($cups, $cup),
            'rounds' => $this->rounds($cups, $cup),
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
     * El cuadro, de la primera ronda a la final, con cómo va cada cruce.
     *
     * @return Collection<int, array{round: CupRound, ties: Collection<int, array{tie: CupTie, result: TieResult}>}>
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
                ]),
            ]);
    }
}
