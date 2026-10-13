<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Services\SeasonResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Namespace is Site, not Public — `public` is a PHP reserved word and is a
 * fatal parse error as a namespace segment (design D5).
 */
abstract class SiteController extends Controller
{
    public function __construct(protected readonly SeasonResolver $seasons) {}

    /**
     * The is_current → latest('id') → 404 chain, in one place for every
     * public page. Domain rule (which season) lives in the Services layer;
     * the HTTP concern (404 when none exist) lives here.
     */
    protected function activeSeason(): Season
    {
        return $this->seasons->active() ?? abort(404);
    }

    /**
     * Clasificación filtraba por `?temporada` hasta el 2026-10-13, y vivía en
     * la raíz. Hay marcadores y enlaces compartidos con ese parámetro puesto, y
     * mandarlos a Historia es lo único que respeta lo que pedían: servirles la
     * temporada vigente en silencio les daría otra cosa sin decirlo.
     *
     * Lo comparten las DOS páginas que pueden recibir esos enlaces —la portada,
     * que ocupa ahora la raíz, y Clasificación en su dirección nueva—, y de ahí
     * que viva aquí: duplicado, el día que cambie se arregla en uno.
     *
     * Sólo si esa temporada existe y no es la vigente. Un id inventado —y las
     * URLs de un sitio público las escribe cualquiera— se queda donde está, en
     * vez de mandar a nadie a un archivo donde tampoco aparece.
     */
    protected function legacySeasonRedirect(Request $request, Season $active): ?RedirectResponse
    {
        $requested = $request->integer('temporada');

        if ($requested <= 0 || $requested === $active->getKey()) {
            return null;
        }

        return Season::whereKey($requested)->exists()
            ? redirect()->route('site.history', ['temporada' => $requested])
            : null;
    }
}
