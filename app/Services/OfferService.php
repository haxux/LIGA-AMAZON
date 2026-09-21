<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Las respuestas a una oferta (Fase 13, design D10).
 *
 * Aceptar no ejecuta nada. Deja la oferta acordada y esperando al
 * administrador, que la firma registrando un traspaso — la única escritura de
 * esta aplicación que mueve dinero y plantillas (design D8). Si aceptar
 * ejecutara, habría dos caminos escribiendo lo mismo y uno de ellos sin nadie
 * mirando.
 */
final class OfferService
{
    public function __construct(private readonly SeasonResolver $seasons) {}

    /**
     * Responde quien NO movió la última oferta: el que ofrece no se contesta a
     * sí mismo, y en una negociación los papeles se alternan.
     */
    public function mayAnswer(Offer $offer, User $user): bool
    {
        if (! $offer->isOpen()) {
            return false;
        }

        if (! $offer->conversation->includes($user)) {
            return false;
        }

        return (int) $offer->moved_by !== (int) $user->getKey();
    }

    public function accept(Offer $offer, User $user): Offer
    {
        $this->guardAnswer($offer, $user);

        $offer->update(['status' => Offer::STATUS_ACCEPTED, 'moved_by' => $user->getKey()]);

        return $offer;
    }

    public function reject(Offer $offer, User $user): Offer
    {
        $this->guardAnswer($offer, $user);

        $offer->update(['status' => Offer::STATUS_REJECTED, 'moved_by' => $user->getKey()]);

        return $offer;
    }

    /**
     * Negociar es contraofertar: la oferta anterior queda en `negociando` y nace
     * otra por el mismo jugador y el mismo club comprador, con el importe nuevo.
     * Reescribir el importe de la anterior borraría por dónde ha pasado la
     * conversación, que es justo lo que un hilo sirve para conservar.
     */
    public function counter(Offer $offer, User $user, int $amount): Offer
    {
        $this->guardAnswer($offer, $user);

        $offer->update(['status' => Offer::STATUS_NEGOTIATING, 'moved_by' => $user->getKey()]);

        return Offer::create([
            'conversation_id' => $offer->conversation_id,
            'player_id' => $offer->player_id,
            'from_club_id' => $offer->from_club_id,
            'amount' => $amount,
            'status' => Offer::STATUS_SENT,
            'moved_by' => $user->getKey(),
        ]);
    }

    /**
     * La firma del administrador: un traspaso de los de siempre, que al
     * guardarse paga, cobra y mueve al jugador.
     */
    public function execute(Offer $offer, User $admin): Transfer
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['offer' => 'Sólo un administrador ejecuta una oferta.']);
        }

        if ($offer->status !== Offer::STATUS_ACCEPTED) {
            throw ValidationException::withMessages(['offer' => 'Sólo se ejecuta una oferta aceptada.']);
        }

        $season = $this->seasons->active();

        if ($season === null) {
            throw ValidationException::withMessages(['offer' => 'No hay temporada vigente en la que registrar el traspaso.']);
        }

        $receiving = $offer->receivingClubId();
        $giving = $offer->isAsking() ? $offer->player?->club_id : $offer->conversation?->other($offer->mover)?->club_id;

        $transfer = Transfer::create([
            'player_id' => $offer->player_id,
            'season_id' => $season->getKey(),
            // Una cesión pactada en el chat es una cesión, no una compra: lo
            // acordado entre los dos técnicos es lo que se ejecuta.
            'type' => $offer->isFree() ? Transfer::TYPE_LOAN_IN : Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            // Ejecutado, no propuesto: aquí la firma ya la está poniendo el
            // administrador, que es lo que faltaba.
            'status' => Transfer::STATUS_EXECUTED,
            // El club que cede es el del jugador AHORA, no el de cuando se
            // ofreció: entre una cosa y otra puede haber pasado otro traspaso.
            'from_club_id' => $offer->player?->club_id ?? $giving,
            'to_club_id' => $receiving,
            'fee' => $offer->amount,
            'loan_term' => $offer->loan_term,
        ]);

        $offer->update([
            'status' => Offer::STATUS_EXECUTED,
            'transfer_id' => $transfer->getKey(),
            'moved_by' => $admin->getKey(),
        ]);

        return $transfer;
    }

    private function guardAnswer(Offer $offer, User $user): void
    {
        if (! $this->mayAnswer($offer, $user)) {
            throw ValidationException::withMessages([
                'offer' => 'Esta oferta no te toca responderla.',
            ]);
        }
    }
}
