<?php

namespace App\Http\Controllers\Site;

use App\Models\Season;
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
     */
    public function __invoke(Request $request, StandingsService $standings): View|RedirectResponse
    {
        $season = $this->activeSeason();
        $requested = $request->integer('temporada');

        // Antes esta página filtraba por `?temporada`, así que puede haber
        // marcadores y enlaces compartidos que la lleven puesta. Mandarlos a
        // Historia es lo único que respeta lo que pedían; servirles la
        // temporada vigente en silencio sería contarles otra cosa sin decirlo.
        //
        // Sólo si esa temporada EXISTE y no es la vigente. Un id inventado
        // —y las URLs de un sitio público las escribe cualquiera— cae en la
        // vigente como caía antes, en vez de mandar a nadie a un archivo donde
        // tampoco está.
        if ($requested > 0 && $requested !== $season->getKey() && Season::whereKey($requested)->exists()) {
            return redirect()->route('site.history', ['temporada' => $requested]);
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
