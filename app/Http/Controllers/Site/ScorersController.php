<?php

namespace App\Http\Controllers\Site;

use App\Services\GoalscorersService;
use Illuminate\Contracts\View\View;

class ScorersController extends SiteController
{
    public function __invoke(GoalscorersService $goalscorers): View
    {
        $season = $this->activeSeason();

        return view('site.scorers', [
            'scorers' => $goalscorers->topScorers($season),
            'assisters' => $goalscorers->topAssisters($season),
            'cleanSheets' => $goalscorers->topCleanSheets($season),
        ]);
    }
}
