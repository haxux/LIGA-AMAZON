<?php

namespace App\Observers;

use App\Models\Transfer;
use App\Services\TransferService;

/**
 * Guardar un traspaso lo EJECUTA (design D8): mueve al jugador y cuadra los dos
 * presupuestos. Va en un observador y no en el recurso de Filament para que
 * valga en cualquier camino de escritura —panel, consola o un seeder futuro—,
 * igual que `TeamObserver` hereda la plantilla al inscribir un club.
 *
 * Sólo en `created`, y sólo si nace ejecutado: editar un traspaso después no
 * vuelve a mover nada —por eso el panel no ofrece edición— y una propuesta del
 * técnico no mueve nada hasta que el administrador la firma.
 *
 * Queda mudo bajo `WithoutModelEvents`, como el resto de observadores y guards
 * de esta aplicación.
 */
class TransferObserver
{
    public function created(Transfer $transfer): void
    {
        // Una propuesta del técnico no mueve nada: espera la firma del
        // administrador, que es quien la ejecuta desde su panel.
        if ($transfer->isProposal()) {
            return;
        }

        app(TransferService::class)->execute($transfer);
    }
}
