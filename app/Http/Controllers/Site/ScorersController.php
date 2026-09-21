<?php

namespace App\Http\Controllers\Site;

use App\Services\Competition;
use App\Services\GoalscorersService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ScorersController extends SiteController
{
    public function __invoke(Request $request, GoalscorersService $goalscorers): View
    {
        $season = $this->activeSeason();

        // Separadas por competición desde la Fase 15 (decisión del propietario):
        // la liga y cada copa por su lado, y «Todo» para la suma.
        $competitions = Competition::forSeason($season);
        $competition = Competition::resolve($competitions, $request->string('competicion')->toString());

        return view('site.scorers', [
            'scorers' => $goalscorers->topScorers($season, 10, null, $competition),
            'assisters' => $goalscorers->topAssisters($season, 10, null, $competition),
            'cleanSheets' => $goalscorers->topCleanSheets($season, 10, null, $competition),
            'competition' => $competition,
            'competitionOptions' => Competition::asSelectOptions($competitions),
        ]);
    }
}
