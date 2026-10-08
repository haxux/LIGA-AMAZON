<?php

namespace App\Observers;

use App\Models\Game;
use App\Services\PushNotificationService;

/**
 * Cuando un partido pasa a tener marcador, el sitio avisa a quien activó
 * notificaciones de partidos. Vive aquí y no en `Game::booted()` por el mismo
 * motivo que `TeamObserver`: es un efecto de negocio sobre otro servicio, no
 * un invariante del dato que el modelo tenga que proteger.
 */
class GameObserver
{
    public function __construct(private readonly PushNotificationService $push) {}

    public function saved(Game $game): void
    {
        // Una anulación automática pone marcador y `voided_at` en la misma
        // escritura: no es un partido que de verdad se jugó, así que no se
        // anuncia como tal.
        if ($game->isVoided() || ! $game->isPlayed()) {
            return;
        }

        $wasPlayedBefore = ! $game->wasRecentlyCreated
            && $game->getOriginal('home_score') !== null
            && $game->getOriginal('away_score') !== null;

        if ($wasPlayedBefore) {
            return;
        }

        $this->push->gameEnded($game);
    }
}
