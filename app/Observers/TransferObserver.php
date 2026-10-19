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

        // Con el dorsal que se eligió en el alta, si se eligió: sin esto el
        // jugador aterrizaba siempre con el primer número libre, aunque el
        // administrador hubiera escrito otro.
        app(TransferService::class)->execute($transfer, $transfer->desiredShirtNumber);
    }

    /**
     * Borrar un traspaso ejecutado deshace TODO lo que movió, no sólo el
     * dinero.
     *
     * El dinero ya se iba solo —los movimientos cuelgan del traspaso con
     * cascada—, pero el jugador se quedaba a medio camino: el 2026-10-17 Lucas
     * Chávez acabó siendo de La Fábrica y marcado como ido al CD Tondela a la
     * vez, fuera de toda plantilla. El propio aviso del panel admitía la
     * mitad que faltaba («It does NOT move the player back»), que es tanto
     * como documentar la trampa en vez de quitarla.
     *
     * Una propuesta no movió nada, así que no hay nada que deshacer.
     */
    public function deleted(Transfer $transfer): void
    {
        if ($transfer->isProposal()) {
            return;
        }

        $service = app(TransferService::class);

        // Sólo el último del jugador: deshacer uno anterior lo devolvería a un
        // club del que un traspaso posterior ya lo sacó.
        if (! $service->isLastExecutedFor($transfer)) {
            return;
        }

        $service->undoSquad($transfer);
    }
}
