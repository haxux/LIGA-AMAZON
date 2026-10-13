<?php

namespace App\Http\Controllers;

use App\Services\GameSchedulingService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * El único sitio donde algo "corre solo": no hay worker ni scheduler en
 * proceso, así que lo que un cron haría cada día se dispara pegándole a esta
 * ruta una vez al día.
 *
 * Es una RUTA y no un comando de Artisan por herencia: nació en Vercel, donde
 * no había máquina a la que entrar y un cron gestionado sólo sabía pedir una
 * URL. En Hostinger sí hay cron de verdad, y el trabajo programado la llama con
 * `curl` desde el propio servidor (ver DESPLIEGUE.md §4). Convertirla en
 * comando sería más idiomático, pero esto funciona y está probado.
 *
 * Quien llame tiene que mandar `Authorization: Bearer <CRON_SECRET>` — es la
 * comprobación de abajo, y sin el secreto configurado la ruta se niega siempre,
 * en vez de quedar abierta a cualquiera que la adivine.
 */
class CronController extends Controller
{
    public function __construct(
        private readonly GameSchedulingService $scheduling,
        private readonly PushNotificationService $push,
    ) {}

    public function tick(Request $request): JsonResponse
    {
        $secret = config('services.cron.secret');

        if (blank($secret) || $request->bearerToken() !== $secret) {
            throw new AccessDeniedHttpException;
        }

        return response()->json([
            'voided' => $this->scheduling->voidOverdueGames(),
            'reminders_sent' => $this->scheduling->sendReminders(),
            // Las noticias PROGRAMADAS: se publican solas al llegar su fecha,
            // sin que nadie escriba nada, así que nadie las anunciaría. Las que
            // se guardan ya publicadas no esperan a esto —las anuncia su
            // observador al momento—, pero entran por la misma puerta y la
            // marca de "ya avisada" impide que se cuenten dos veces.
            //
            // Esto corre UNA vez al día, así que una noticia programada puede
            // anunciarse hasta un día tarde. Publicarla a mano la anuncia al
            // instante.
            'news_announced' => $this->push->publishedNews(),
        ]);
    }
}
