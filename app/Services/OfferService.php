<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

    /**
     * Aceptar cierra el trato entre los dos técnicos y lo deja PROPUESTO en la
     * bandeja del administrador. No mueve plantilla ni dinero: eso lo hace el
     * traspaso al aprobarse, que sigue siendo la única puerta.
     *
     * La propuesta nace aquí y no se teclea otra vez (decisión del propietario,
     * 2026-10-13): lo acordado ya está escrito, y volver a escribirlo es donde
     * se eligen dos veces el mismo club.
     */
    public function accept(Offer $offer, User $user): Offer
    {
        $this->guardAnswer($offer, $user);

        return DB::transaction(function () use ($offer, $user): Offer {
            $transfer = $this->propose($offer, $user);

            $offer->update([
                'status' => Offer::STATUS_ACCEPTED,
                'moved_by' => $user->getKey(),
                'transfer_id' => $transfer->getKey(),
            ]);

            return $offer;
        });
    }

    /**
     * El trato acordado, escrito como propuesta de traspaso para que el
     * administrador la firme desde Transfers.
     */
    private function propose(Offer $offer, User $user): Transfer
    {
        $season = $this->seasons->active();

        if ($season === null) {
            throw ValidationException::withMessages(['offer' => 'No hay temporada vigente en la que registrar el traspaso.']);
        }

        $giving = $offer->givingClubId();

        // Entre la oferta y el apretón de manos el jugador pudo irse a un
        // tercer club, y entonces lo acordado no significa nada: ninguno de los
        // dos que hablan lo tiene ya. Sin esta comprobación se propondría un
        // traspaso entre los dos del hilo por un jugador que no es de ninguno.
        $clubs = $offer->conversation?->participants
            ->pluck('club_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all() ?? [];

        if ($giving === null || ! in_array($giving, $clubs, true)) {
            throw ValidationException::withMessages([
                'offer' => 'Ese jugador ya no es de ninguno de los dos clubes: la oferta se quedó vieja.',
            ]);
        }

        $receiving = $offer->receivingClubId();

        // El otro lado tiene que ser un club. Con un presidente al otro lado no
        // lo hay —un presidente no dirige ninguno— y no hay adónde mandarlo.
        if ($receiving === null) {
            throw ValidationException::withMessages([
                'offer' => 'El otro lado de la conversación no dirige ningún club.',
            ]);
        }

        return Transfer::create([
            'player_id' => $offer->player_id,
            'season_id' => $season->getKey(),
            // Una cesión pactada en el chat es una cesión, no una compra: lo
            // acordado entre los dos técnicos es lo que se propone.
            'type' => $offer->isFree() ? Transfer::TYPE_LOAN_IN : Transfer::TYPE_SIGNING,
            'scope' => Transfer::SCOPE_INTERNAL,
            // Propuesto, no ejecutado: falta la firma del administrador, que la
            // pone desde Transfers y es la que mueve el dinero.
            'status' => Transfer::STATUS_PROPOSED,
            'from_club_id' => $giving,
            'to_club_id' => $receiving,
            'fee' => $offer->amount,
            'loan_term' => $offer->loan_term,
            'proposed_by' => $user->getKey(),
        ]);
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

    private function guardAnswer(Offer $offer, User $user): void
    {
        if (! $this->mayAnswer($offer, $user)) {
            throw ValidationException::withMessages([
                'offer' => 'Esta oferta no te toca responderla.',
            ]);
        }
    }
}
