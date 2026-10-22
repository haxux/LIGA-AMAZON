<?php

namespace App\Observers;

use App\Models\Offer;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Cada movimiento de una oferta avisa al otro lado del hilo (decisión del
 * propietario, 2026-10-22): una oferta nueva, una contraoferta, una aceptación
 * o un rechazo. Como `MessageObserver`: el aviso es un efecto de negocio sobre
 * otro servicio, y así llega venga de donde venga el cambio.
 *
 * Después del commit: aceptar, rechazar y contraofertar van en transacción
 * (OfferService), y un aviso de algo que luego se deshizo sería mentira.
 */
class OfferObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly PushNotificationService $push) {}

    public function created(Offer $offer): void
    {
        // Una contraoferta nace como oferta nueva (OfferService::counter) y
        // deja la anterior del mismo jugador en «negociando». Se mira sólo la
        // inmediatamente anterior: una oferta nueva por un jugador cuya
        // negociación ya terminó (aceptada o rechazada) es una oferta, no una
        // contraoferta.
        $previous = Offer::query()
            ->where('conversation_id', $offer->conversation_id)
            ->where('id', '<', $offer->getKey())
            ->when($offer->player_id, fn ($query) => $query->where('player_id', $offer->player_id))
            ->when($offer->player_id === null, fn ($query) => $query->where('fc_id', $offer->fc_id))
            ->latest('id')
            ->value('status');

        $countered = $previous === Offer::STATUS_NEGOTIATING;

        $this->push->offerMoved($offer, $countered ? 'countered' : 'sent');
    }

    public function updated(Offer $offer): void
    {
        if (! $offer->wasChanged('status')) {
            return;
        }

        // «Negociando» no avisa: lo que avisa es la contraoferta que nace a la
        // vez. «Ejecutada» la firma el administrador y ya deja su mensaje en
        // el hilo, que avisa por su cuenta.
        match ($offer->status) {
            Offer::STATUS_ACCEPTED => $this->push->offerMoved($offer, 'accepted'),
            Offer::STATUS_REJECTED => $this->push->offerMoved($offer, 'rejected'),
            default => null,
        };
    }
}
