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
#[Fillable(['conversation_id', 'player_id', 'from_club_id', 'amount', 'status', 'moved_by', 'transfer_id'])]
class Offer extends Model
{
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
            if ($offer->amount === null || $offer->amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'El importe tiene que ser mayor que cero.']);
            }

            if (! array_key_exists($offer->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => "El estado {$offer->status} no existe."]);
            }

            if ($offer->exists) {
                return;
            }

            $ownerClubId = Player::query()->whereKey($offer->player_id)->value('club_id');

            if ($ownerClubId !== null && (int) $ownerClubId === (int) $offer->from_club_id) {
                throw ValidationException::withMessages([
                    'player_id' => 'No se ofrece por un jugador propio: se ofrece por uno de la plantilla del otro club.',
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
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
