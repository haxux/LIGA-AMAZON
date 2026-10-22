<?php

namespace App\Http\Controllers\Site;

use App\Models\Cup;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\News;
use App\Models\Season;
use App\Services\Competition;
use App\Services\GoalscorersService;
use App\Services\SeasonOverview;
use App\Services\StandingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * La portada: la bienvenida al sitio, a la que lleva el botón de la marca
 * (decisión del propietario, 2026-10-13). Hasta hoy la raíz era Clasificación,
 * que se mudó a /clasificacion con su mismo nombre de ruta.
 *
 * No manda ningún dato nuevo: resume lo que ya hay en el resto del sitio y
 * enlaza a donde está completo. De ahí que reutilice los servicios y las
 * tarjetas tal cual, en vez de calcular por su cuenta.
 *
 * UN criterio recorre toda la página, porque es lo que decide si una cifra
 * engaña: un bloque cuyas filas dicen de qué competición son puede mezclar liga
 * y copa —cada tarjeta de resultado lleva su etiqueta—, y un bloque que agrega
 * en UNA cifra es de liga y lo dice. Sumar la copa a la liga sin avisar es
 * exactamente lo que el propietario pidió no hacer en la Fase 15.
 */
class HomeController extends SiteController
{
    public function __invoke(
        Request $request,
        StandingsService $standings,
        GoalscorersService $goalscorers,
        SeasonOverview $overview,
    ): View|RedirectResponse {
        $season = $this->activeSeason();

        // La raíz llevaba `?temporada` cuando era Clasificación.
        if ($redirect = $this->legacySeasonRedirect($request, $season)) {
            return $redirect;
        }

        $tables = $standings->tablesFor($season);

        // Los siguientes por jugar, no «la jornada de ahora»: una jornada se
        // juega a trozos y así salían ahí partidos con su resultado puesto,
        // repetidos en el bloque de al lado.
        // Cuatro (decisión del propietario, 2026-10-20), como los resultados:
        // las filas de marcador de al lado de la noticia, y el resto en
        // Partidos.
        $upcoming = $overview->upcomingGames($season, 4);

        // Dos preguntas distintas, las dos respondidas por los mismos partidos
        // para que no puedan contradecirse:
        //
        // El rótulo del bloque nombra la jornada SÓLO si todos son de ella; con
        // un aplazado de otra mezclado, decir «jornada 11» mentiría sobre uno.
        $numbers = $upcoming->map(fn (Game $game) => $game->matchday?->number)->filter()->unique();
        $matchday = $numbers->count() === 1 ? $upcoming->first()->matchday : null;

        // La cabecera dice por dónde va la temporada, y eso es la jornada más
        // atrasada que queda por jugar: la del primero de la lista.
        $jornada = $upcoming->first()?->matchday?->number;

        return view('site.home', [
            'season' => $season,
            'progress' => $overview->progress($season),
            'matchday' => $matchday,
            'jornada' => $jornada,
            'upcoming' => $upcoming,
            // Cuatro y no seis: en el teléfono esto se apila bajo los seis de
            // la jornada, y había que pasar doce tarjetas para llegar al podio
            // y a lo que da gusto mirar.
            'results' => $overview->playedGames($season)->take(4),

            // El podio: los tres primeros de cada división, y nada de las que
            // no han jugado, cuyo orden lo decidiría el id.
            'podiums' => collect($tables)
                ->filter(fn (array $table) => ($table['rows']->first()?->played ?? 0) > 0)
                ->map(fn (array $table) => [
                    'heading' => $table['heading'],
                    'rows' => $table['rows']->take(3),
                ])
                ->values()
                ->all(),

            'leaders' => $this->leaders($goalscorers, $season),

            'form' => $overview->form($season),
            'highlights' => $overview->highlights($season, $tables),

            'cups' => Cup::query()
                ->where('season_id', $season->getKey())
                ->withCount('participants')
                ->orderBy('name')
                ->get(),

            // Las cinco primeras, en el carrusel de arriba; las cuatro
            // siguientes, al final (la cuarta sólo en el móvil y la app, ver
            // la vista).
            'news' => News::published()->with('team.club')->latest('published_at')->take(9)->get(),
        ]);
    }

    /**
     * Las tres cabeceras de la liga, con los empatados incluidos.
     *
     * De la liga y no de todo, porque son tres cifras sueltas sin etiqueta de
     * competición: un goleador de liga y uno de copa no son la misma cifra.
     *
     * En una sola pasada y no con tres llamadas: los tres conjuntos de
     * jugadores se solapan casi por completo, y pidiéndolos por separado la
     * misma consulta de clubes salía siete veces en una petición.
     *
     * @return array<int, array{label: string, rows: Collection}>
     */
    private function leaders(GoalscorersService $goalscorers, Season $season): array
    {
        $labels = [
            GameEvent::TYPE_GOAL => 'Máximo goleador',
            GameEvent::TYPE_ASSIST => 'Máximo asistente',
            GameEvent::TYPE_CLEAN_SHEET => 'Más porterías a cero',
        ];

        $boards = $goalscorers->leaderboards($season, array_keys($labels), Competition::league(), 3);

        return collect($labels)
            ->map(fn (string $label, string $type) => [
                'label' => $label,
                'rows' => $boards[$type] ?? collect(),
            ])
            ->values()
            ->all();
    }
}
