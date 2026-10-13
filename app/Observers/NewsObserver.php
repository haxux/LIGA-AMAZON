<?php

namespace App\Observers;

use App\Models\News;
use App\Services\PushNotificationService;

/**
 * Al publicar una noticia se avisa por push a quien tenga las notificaciones
 * puestas, tenga sesión o no (decisión del propietario, 2026-10-13).
 *
 * Observa el guardado entero y no sólo la creación: una noticia puede nacer
 * como borrador y publicarse al editarla, y ésa es tan nueva para el lector
 * como la que nace publicada.
 *
 * No decide nada por su cuenta —qué está pendiente y a quién avisar lo dice el
 * servicio—, porque el barrido del cron entra por la misma puerta para las
 * programadas. Dos criterios distintos acabarían avisando distinto.
 */
class NewsObserver
{
    public function __construct(private readonly PushNotificationService $push) {}

    public function saved(News $news): void
    {
        $this->push->publishedNews();
    }
}
