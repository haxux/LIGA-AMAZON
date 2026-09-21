<?php

namespace App\Models;

use Database\Factories\BudgetMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Una entrada del libro de movimientos de un club (Fase 12, design D7).
 *
 * El importe se guarda siempre en positivo y es el `type` el que dice hacia
 * dónde va. Guardar egresos en negativo dejaría dos formas de representar lo
 * mismo, y la primera suma mal hecha no se notaría.
 */
#[Fillable(['club_id', 'season_id', 'transfer_id', 'type', 'amount', 'reason', 'status', 'created_by'])]
class BudgetMovement extends Model
{
    /** @use HasFactory<BudgetMovementFactory> */
    use HasFactory;

    public const TYPE_INCOME = 'ingreso';

    public const TYPE_EXPENSE = 'egreso';

    public const STATUS_PROPOSED = 'propuesto';

    public const STATUS_APPROVED = 'aprobado';

    public const STATUS_REJECTED = 'rechazado';

    /**
     * Vocabulario en PHP, no enums de base de datos — mismo criterio que
     * `GameEvent::TYPES` y `SquadMembership::TYPES`.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        self::TYPE_INCOME => 'Ingreso',
        self::TYPE_EXPENSE => 'Egreso',
    ];

    /**
     * `rechazado` no estaba en el diseño, que nombraba dos estados. Se añade
     * porque el administrador necesita poder decir que no, y la alternativa
     * —borrar la propuesta— destruye justo el rastro que un libro de
     * movimientos existe para conservar. El saldo sigue contando sólo los
     * aprobados, así que la regla del diseño no cambia.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        self::STATUS_PROPOSED => 'Propuesto',
        self::STATUS_APPROVED => 'Aprobado',
        self::STATUS_REJECTED => 'Rechazado',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * Invariantes de entidad, como los de `Game`, `Matchday` y `User`: un
     * movimiento de cero o negativo no es un movimiento, y un tipo o un estado
     * fuera del vocabulario convertiría el saldo en una cifra inventada.
     */
    protected static function booted(): void
    {
        static::saving(function (BudgetMovement $movement): void {
            if ($movement->amount === null || $movement->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El importe tiene que ser mayor que cero.',
                ]);
            }

            if (! array_key_exists($movement->type, self::TYPES)) {
                throw ValidationException::withMessages([
                    'type' => "El tipo {$movement->type} no existe.",
                ]);
            }

            if (! array_key_exists($movement->status, self::STATUSES)) {
                throw ValidationException::withMessages([
                    'status' => "El estado {$movement->status} no existe.",
                ]);
            }
        });
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Lo que este movimiento le hace al saldo: nada mientras no esté aprobado.
     */
    public function signedAmount(): int
    {
        if (! $this->isApproved()) {
            return 0;
        }

        return $this->type === self::TYPE_INCOME ? $this->amount : -$this->amount;
    }
}
