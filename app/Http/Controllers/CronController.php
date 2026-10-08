<?php

namespace App\Http\Controllers;

use App\Services\GameSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * El único sitio donde algo "corre solo" en este despliegue: no hay worker ni
 * scheduler en proceso (ver `.env.production.example`), así que lo que un
 * cron de verdad haría cada día lo dispara Vercel Cron pegándole a esta ruta
 * (ver `vercel.json`) una vez al día.
 *
 * Vercel manda de vuelta `Authorization: Bearer <CRON_SECRET>` en cada
 * invocación programada cuando el proyecto define esa variable — es la
 * comprobación de abajo, y sin el secreto configurado la ruta se niega
 * siempre, en vez de quedar abierta a cualquiera que la adivine.
 */
class CronController extends Controller
{
    public function __construct(private readonly GameSchedulingService $scheduling) {}

    public function tick(Request $request): JsonResponse
    {
        $secret = config('services.cron.secret');

        if (blank($secret) || $request->bearerToken() !== $secret) {
            throw new AccessDeniedHttpException;
        }

        return response()->json([
            'voided' => $this->scheduling->voidOverdueGames(),
            'reminders_sent' => $this->scheduling->sendReminders(),
        ]);
    }
}
