<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Una oferta por un jugador, hecha dentro de un hilo (Fase 13, design D10).
 *
 * Es una máquina de estados y nada más: aceptarla NO toca plantillas ni
 * presupuestos. La deja acordada y la hace aparecer en la bandeja del
 * administrador, que la ejecuta registrando el traspaso — que es lo único que
 * mueve dinero y jugadores en esta aplicación.
 */
#[Fillable([
    'conversation_id', 'player_id', 'kind', 'from_club_id', 'amount', 'loan_term',
    'status', 'moved_by', 'transfer_id',
])]
class Offer extends Model
{
    public const KIND_BUY = 'compra';

    public const KIND_SELL = 'venta';

    public const KIND_LOAN_IN = 'cesion';

    public const KIND_LOAN_OUT = 'ceder';

    /**
     * Las cuatro operaciones, dichas DESDE EL LADO DE QUIEN LAS PROPONE y con
     * el sujeto puesto.
     *
     * «Cesión» y «Ceder» se escriben casi igual y significan direcciones
     * opuestas: una trae un jugador y la otra lo manda. Nombradas así no hay
     * nada que recordar, y la frase dice sola de qué plantilla se elige.
     *
     * @var array<string, string>
     */
    public const KINDS = [
        self::KIND_BUY => 'Comprarle un jugador suyo',
        self::KIND_SELL => 'Venderle un jugador mío',
        self::KIND_LOAN_IN => 'Pedirle un jugador cedido',
        self::KIND_LOAN_OUT => 'Cederle un jugador mío',
    ];

    /**
     * Las mismas, en una palabra, para las tablas y los avisos donde la frase
     * entera no cabe.
     *
     * @var array<string, string>
     */
    public const KINDS_SHORT = [
        self::KIND_BUY => 'Compra',
        self::KIND_SELL => 'Venta',
        self::KIND_LOAN_IN => 'Cesión',
        self::KIND_LOAN_OUT => 'Ceder',
    ];

    /**
     * Las que piden al interlocutor uno de SUS jugadores, frente a las que le
     * ofrecen uno propio. Decide sobre qué plantilla se elige.
     *
     * @var array<int, string>
     */
    public const ASKING = [self::KIND_BUY, self::KIND_LOAN_IN];

    /** @var array<int, string> */
    public const FREE = [self::KIND_LOAN_IN, self::KIND_LOAN_OUT];

    public const STATUS_SENT = 'enviada';

    public const STATUS_ACCEPTED = 'aceptada';

    public const STATUS_REJECTED = 'rechazada';

    public const STATUS_NEGOTIATING = 'negociando';

    public const STATUS_EXECUTED = 'ejecutada';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_SENT => 'Enviada',
        self::STATUS_ACCEPTED => 'Aceptada',
        self::STATUS_REJECTED => 'Rechazada',
        self::STATUS_NEGOTIATING => 'Negociando',
        self::STATUS_EXECUTED => 'Ejecutada',
    ];

    /**
     * Una oferta sin operación dicha es una compra: es la que había antes de
     * que el chat supiera de las otras tres.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => self::KIND_BUY,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * Invariantes de entidad, como los de `BudgetMovement` y `Transfer`: una
     * oferta de cero no es una oferta, y un club no se ofrece a sí mismo un
     * jugador que ya es suyo.
     */
    protected static function booted(): void
    {
        static::saving(function (Offer $offer): void {
            if (! array_key_exists($offer->kind, self::KINDS)) {
                throw ValidationException::withMessages(['kind' => "La operación {$offer->kind} no existe."]);
            }

            // Una cesión no tiene coste: lo que se pacta es el plazo, y el
            // importe se guarda en cero para que no haya dos formas de decirlo.
            if ($offer->isFree()) {
                $offer->amount = 0;
            } elseif ($offer->amount === null || $offer->amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'El importe tiene que ser mayor que cero.']);
            }

            if (! array_key_exists($offer->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => "El estado {$offer->status} no existe."]);
            }

            if ($offer->exists) {
                return;
            }

            $ownerClubId = (int) Player::query()->whereKey($offer->player_id)->value('club_id');
            $mine = $ownerClubId === (int) $offer->from_club_id;

            // Pedir es pedir lo del otro; ofrecer es ofrecer lo propio. Al
            // revés, la operación no significa nada.
            if ($offer->isAsking() && $mine) {
                throw ValidationException::withMessages([
                    'player_id' => 'Para pedir se elige un jugador del otro club; el tuyo se ofrece.',
                ]);
            }

            if (! $offer->isAsking() && ! $mine) {
                throw ValidationException::withMessages([
                    'player_id' => 'Sólo puedes ofrecer a un jugador de tu propia plantilla.',
                ]);
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * El club que COMPRA. Se mantiene a lo largo de una negociación aunque cada
     * contraoferta la mueva uno distinto.
     */
    public function fromClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'from_club_id');
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Sólo la oferta viva admite respuesta: una ya contestada es historia del
     * hilo.
     */
    public function isAsking(): bool
    {
        return in_array($this->kind, self::ASKING, true);
    }

    public function isFree(): bool
    {
        return in_array($this->kind, self::FREE, true);
    }

    /**
     * El club que SUELTA al jugador: el dueño de su ficha, sin más.
     *
     * Quien tiene al jugador es quien lo da, lo haya pedido el otro o lo esté
     * ofreciendo él. Y es el club de AHORA, no el de cuando se hizo la oferta:
     * entre una cosa y la otra puede haber pasado otro traspaso.
     */
    public function givingClubId(): ?int
    {
        return $this->player?->club_id === null ? null : (int) $this->player->club_id;
    }

    /**
     * El club que se queda al jugador: el otro de los dos que hablan.
     *
     * Una sola regla para las cuatro operaciones, y no una por tipo. La que
     * había ramificaba según se pidiera o se ofreciera, y en las de OFRECER
     * —venta y ceder— devolvía el club del propio jugador, o sea el que lo
     * suelta. El 2026-10-13 eso ejecutó la venta de Sandro como un traspaso de
     * Drakukeo a Drakukeo: el jugador no salía de un sitio ni llegaba al otro
     * porque los dos lados eran el mismo.
     *
     * Dicho así no hay nada que ramificar: da el dueño, recibe el otro.
     */
    public function receivingClubId(): ?int
    {
        $giving = $this->givingClubId();

        if ($giving === null) {
            return null;
        }

        return $this->conversation?->participants
            ->pluck('club_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->first(fn (int $id): bool => $id !== $giving);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
