<?php

namespace App\Services;

use App\Models\FcPlayer;
use App\Models\Offer;
use App\Models\Player;
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
            $this->lockOpen($offer, $user);

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

        // Entre la oferta y el apretón de manos el jugador pudo irse cedido.
        if ($offer->player?->isOnLoan()) {
            throw ValidationException::withMessages([
                'offer' => $offer->player->name.' está cedido: no se puede cerrar hasta que acabe la cesión.',
            ]);
        }

        // Y que el comprador siga teniendo el dinero que tenía al ofertar.
        $offer->guardBuyerCanAfford();

        if ($offer->withPresident()) {
            return $this->proposeWithPresident($offer, $season->getKey());
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

    /**
     * El trato con el presidente, escrito como traspaso EXTERNO: él no dirige
     * ningún club, así que el otro extremo no es una fila de `clubs`.
     *
     * - Lo que se pide (compra, cesión): el jugador del catálogo llega al club
     *   del técnico. Si ya tuvo ficha en la liga y se fue, se reutiliza; si no,
     *   la crea el presidente al aprobar, con el `fc_id` ya puesto.
     * - Lo que se ofrece (venta, ceder): sale de la plantilla del técnico y no
     *   va a ningún sitio concreto; su historia se conserva.
     *
     * El proponente es el TÉCNICO, aunque acepte el presidente: es a quien el
     * traspaso avisa en el chat cuando se resuelve.
     */
    private function proposeWithPresident(Offer $offer, int $seasonId): Transfer
    {
        $coach = $offer->conversation->participants->first(fn (User $participant) => ! $participant->isAdmin());
        $type = match ($offer->kind) {
            Offer::KIND_BUY => Transfer::TYPE_SIGNING,
            Offer::KIND_SELL => Transfer::TYPE_SALE,
            Offer::KIND_LOAN_IN => Transfer::TYPE_LOAN_IN,
            default => Transfer::TYPE_LOAN_OUT,
        };

        $attributes = [
            'season_id' => $seasonId,
            'type' => $type,
            'scope' => Transfer::SCOPE_EXTERNAL,
            'status' => Transfer::STATUS_PROPOSED,
            'fee' => $offer->amount,
            'loan_term' => $offer->loan_term,
            'proposed_by' => $coach?->getKey(),
        ];

        if (! $offer->isAsking()) {
            // Se ofrece lo propio: tiene que seguir siendo suyo.
            if ($offer->player === null || (int) $offer->player->club_id !== (int) $offer->from_club_id || $offer->player->left_at !== null) {
                throw ValidationException::withMessages([
                    'offer' => 'Ese jugador ya no es de tu plantilla: la oferta se quedó vieja.',
                ]);
            }

            return Transfer::create($attributes + [
                'player_id' => $offer->player_id,
                'from_club_id' => $offer->from_club_id,
            ]);
        }

        if (Player::fcIdIsInLeague((int) $offer->fc_id)) {
            throw ValidationException::withMessages([
                'offer' => $offer->external_player.' ya juega en un club de la liga: la oferta se quedó vieja.',
            ]);
        }

        $former = Player::query()->where('fc_id', $offer->fc_id)->whereNotNull('left_at')->latest('id')->first();
        $catalogue = FcPlayer::query()->where('fc_id', $offer->fc_id)->first();

        return Transfer::create($attributes + [
            'player_id' => $former?->getKey(),
            'external_player' => $former === null ? $offer->external_player : null,
            'fc_id' => $offer->fc_id,
            'to_club_id' => $offer->from_club_id,
            'external_club' => $catalogue?->club ?: 'Agente libre',
        ]);
    }

    public function reject(Offer $offer, User $user): Offer
    {
        $this->guardAnswer($offer, $user);

        return DB::transaction(function () use ($offer, $user): Offer {
            $this->lockOpen($offer, $user);

            $offer->update(['status' => Offer::STATUS_REJECTED, 'moved_by' => $user->getKey()]);

            return $offer;
        });
    }

    /**
     * Negociar es contraofertar: la oferta anterior queda en `negociando` y nace
     * otra por el mismo jugador y el mismo club comprador, con el importe nuevo.
     * Reescribir el importe de la anterior borraría por dónde ha pasado la
     * conversación, que es justo lo que un hilo sirve para conservar.
     */
    public function counter(Offer $offer, User $user, ?int $amount = null, ?string $term = null): Offer
    {
        $this->guardAnswer($offer, $user);

        return DB::transaction(function () use ($offer, $user, $amount, $term): Offer {
            $this->lockOpen($offer, $user);

            $offer->update(['status' => Offer::STATUS_NEGOTIATING, 'moved_by' => $user->getKey()]);

            // La contraoferta es la misma operación sobre el mismo jugador: lo que
            // cambia es el importe, o el plazo si es una cesión. Copiar sólo
            // jugador e importe —como se hacía— convertía cualquier contraoferta en
            // una compra.
            return Offer::create([
                'conversation_id' => $offer->conversation_id,
                'player_id' => $offer->player_id,
                'fc_id' => $offer->fc_id,
                'external_player' => $offer->external_player,
                'kind' => $offer->kind,
                'from_club_id' => $offer->from_club_id,
                'amount' => $offer->isFree() ? 0 : $amount,
                'loan_term' => $offer->isFree() ? ($term ?? $offer->loan_term) : null,
                'tension' => $offer->tension,
                'status' => Offer::STATUS_SENT,
                'moved_by' => $user->getKey(),
            ]);
        });
    }

    /**
     * La tensión de la negociación, de 1 a 100, que fija el presidente sobre la
     * oferta viva. Es un dato para el técnico: no cierra ni bloquea nada.
     */
    public function setTension(Offer $offer, User $user, int $tension): Offer
    {
        if (! $user->isAdmin() || ! $offer->conversation->includes($user) || ! $offer->isOpen()) {
            throw ValidationException::withMessages(['offer' => 'Sólo el presidente fija la tensión de una negociación abierta.']);
        }

        $offer->update(['tension' => $tension]);

        return $offer;
    }

    /**
     * `guardAnswer()` otra vez, contra la fila de AHORA y bloqueada hasta el
     * final de la transacción: con el modelo que trajo la pantalla, dos clics
     * en «Aceptar» la veían abierta los dos y proponían el traspaso dos veces.
     */
    private function lockOpen(Offer $offer, User $user): void
    {
        $current = Offer::query()->lockForUpdate()->find($offer->getKey());

        $offer->forceFill(['status' => $current?->status, 'moved_by' => $current?->moved_by])
            ->syncOriginalAttributes(['status', 'moved_by']);

        $this->guardAnswer($offer, $user);
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
