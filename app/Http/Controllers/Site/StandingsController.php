<?php

namespace App\Http\Controllers\Site;

use App\Services\StandingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StandingsController extends SiteController
{
    /**
     * La clasificación es la de AHORA y no tiene filtro de temporada (decisión
     * del propietario): quien quiera las de antes va a Historia, que es una
     * página aparte con su propio desplegable.
     *
     * Vivía en la raíz hasta el 2026-10-13, que pasó a ocuparla la portada. El
     * nombre de la ruta no cambió, así que todos los enlaces del sitio siguen
     * valiendo; lo que cambió es la dirección.
     */
    public function __invoke(Request $request, StandingsService $standings): View|RedirectResponse
    {
        $season = $this->activeSeason();

        if ($redirect = $this->legacySeasonRedirect($request, $season)) {
            return $redirect;
        }

        return view('site.standings', [
            'season' => $season,
            'tables' => $standings->tablesFor($season),
            // El botón sólo aparece si hay algo detrás: con el archivo vacío
            // sería una puerta a una habitación vacía.
            'hasHistory' => $this->seasons->archived()->isNotEmpty(),
        ]);
    }
}
