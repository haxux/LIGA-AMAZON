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

        // División y jornada ("fecha") sólo existen en la liga: las opciones
        // inventadas en la URL caen en «todas», como la competición.
        $divisions = $season->divisions()->orderBy('id')->get();
        $division = $divisions->firstWhere('id', $request->integer('division'));

        $numbers = $season->matchdays()
            ->when($division, fn ($query) => $query->where('division_id', $division->getKey()))
            ->orderBy('number')
            ->pluck('number')
            ->unique()
            ->values();
        $number = $numbers->contains($request->integer('fecha')) ? $request->integer('fecha') : null;

        return view('site.scorers', [
            'scorers' => $goalscorers->topScorers($season, 10, null, $competition, $division?->getKey(), $number),
            'assisters' => $goalscorers->topAssisters($season, 10, null, $competition, $division?->getKey(), $number),
            'cleanSheets' => $goalscorers->topCleanSheets($season, 10, null, $competition, $division?->getKey(), $number),
            'competition' => $competition,
            'competitionOptions' => Competition::asSelectOptions($competitions),
            'divisionOptions' => ['' => 'Todas'] + $divisions->pluck('name', 'id')->all(),
            'selectedDivision' => $division?->getKey(),
            'fechaOptions' => ['' => 'Todas'] + $numbers->mapWithKeys(fn (int $n) => [$n => 'Jornada '.$n])->all(),
            'selectedFecha' => $number,
        ]);
    }
}
