<?php

namespace App\Models;

use App\Observers\TransferObserver;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Un fichaje, una venta o un préstamo (Fase 12, design D8).
 *
 * Dentro de la liga es UNA fila leída desde los dos lados: el club que paga en
 * `to_club_id`, el que cobra en `from_club_id`. Por eso un traspaso interno se
 * registra como FICHAJE del comprador y no además como venta del vendedor —
 * esa segunda fila es exactamente la que D8 descarta, y el dinero se contaría
 * dos veces. Desde el lado del vendedor la ficha lo muestra como salida, que es
 * cómo se lee desde ahí.
 *
 * `venta` queda, en consecuencia, para las salidas FUERA de la liga, donde no
 * hay un club comprador al que apuntar.
 */
#[Fillable([
    'player_id', 'external_player', 'season_id', 'type', 'scope', 'status',
    'from_club_id', 'to_club_id', 'external_club', 'fee', 'loan_term', 'proposed_by',
])]
#[ObservedBy(TransferObserver::class)]
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    public const TYPE_SIGNING = 'fichaje';

    public const TYPE_SALE = 'venta';

    public const TYPE_LOAN_IN = 'cesion';

    public const TYPE_LOAN_OUT = 'ceder';

    public const SCOPE_INTERNAL = 'interna';

    public const SCOPE_EXTERNAL = 'externa';

    public const STATUS_PROPOSED = 'propuesto';

    public const STATUS_EXECUTED = 'ejecutado';

    public const STATUS_REJECTED = 'rechazado';

    public const TERM_SIX_MONTHS = '6m';

    public const TERM_ONE_YEAR = '1a';

    /**
     * Las cuatro operaciones, cada una con su dirección: el préstamo se parte
     * en dos porque no es lo mismo traer a alguien cedido que cederlo, y el
     * formulario, el dinero y la plantilla se mueven distinto en cada caso.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        self::TYPE_SIGNING => 'Fichaje',
        self::TYPE_SALE => 'Venta',
        self::TYPE_LOAN_IN => 'Cesión',
        self::TYPE_LOAN_OUT => 'Ceder',
    ];

    /**
     * Las que traen a un jugador al club, frente a las que se lo llevan.
     *
     * @var array<int, string>
     */
    public const INCOMING = [self::TYPE_SIGNING, self::TYPE_LOAN_IN];

    /** @var array<int, string> */
    public const FREE = [self::TYPE_LOAN_IN, self::TYPE_LOAN_OUT];

    /** @var array<string, string> */
    public const SCOPES = [
        self::SCOPE_INTERNAL => 'Dentro de la liga',
        self::SCOPE_EXTERNAL => 'Fuera de la liga',
    ];

    /**
     * Un traspaso del administrador nace ejecutado; el del técnico, propuesto.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        self::STATUS_PROPOSED => 'Propuesto',
        self::STATUS_EXECUTED => 'Ejecutado',
        self::STATUS_REJECTED => 'Rechazado',
    ];

    /** @var array<string, string> */
    public const LOAN_TERMS = [
        self::TERM_SIX_MONTHS => '6 meses',
        self::TERM_ONE_YEAR => '1 año',
    ];

    /**
     * En memoria y no sólo como valor por omisión de la columna: el observador
     * lee `status` justo después de crear, y un valor que sólo vive en la base
     * llegaría nulo y no ejecutaría nada.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_EXECUTED,
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'integer',
        ];
    }

    /**
     * Invariantes de entidad, del mismo estilo que los de `Game` y `User`: un
     * traspaso al que le falta una punta no se puede ejecutar, y ejecutarlo a
     * medias dejaría dinero movido y plantillas sin mover.
     */
    protected static function booted(): void
    {
        static::saving(function (Transfer $transfer): void {
            if (! array_key_exists($transfer->type, self::TYPES)) {
                throw ValidationException::withMessages(['type' => "El tipo {$transfer->type} no existe."]);
            }

            if (! array_key_exists($transfer->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => "El estado {$transfer->status} no existe."]);
            }

            if (! array_key_exists($transfer->scope, self::SCOPES)) {
                throw ValidationException::withMessages(['scope' => "El ámbito {$transfer->scope} no existe."]);
            }

            if ($transfer->player_id === null && blank($transfer->external_player)) {
                throw ValidationException::withMessages([
                    'player_id' => 'Un traspaso necesita un jugador: de la liga, o el nombre de uno de fuera.',
                ]);
            }

            if ($transfer->scope === self::SCOPE_INTERNAL) {
                if ($transfer->type === self::TYPE_SALE) {
                    throw ValidationException::withMessages([
                        'type' => 'Una venta entre clubes de la liga se registra como el fichaje del comprador: es una sola operación.',
                    ]);
                }

                if ($transfer->from_club_id === null || $transfer->to_club_id === null) {
                    throw ValidationException::withMessages([
                        'to_club_id' => 'Un traspaso dentro de la liga necesita club de origen y club de destino.',
                    ]);
                }

                return;
            }

            // El club de fuera es obligatorio cuando el jugador VIENE de allí:
            // sin él, la propuesta no dice de quién se está hablando. Cuando
            // sale, puede no saberse todavía —es justo lo que la dirección va a
            // buscar— y se completa al concretar la operación.
            if ($transfer->isIncoming() && blank($transfer->external_club) && $transfer->from_club_id === null) {
                throw ValidationException::withMessages([
                    'external_club' => 'Hace falta saber de qué club viene el jugador.',
                ]);
            }

            if ($transfer->isIncoming() && $transfer->to_club_id === null) {
                throw ValidationException::withMessages([
                    'to_club_id' => 'Falta el club que recibe al jugador.',
                ]);
            }

            if (! $transfer->isIncoming() && $transfer->from_club_id === null) {
                throw ValidationException::withMessages([
                    'from_club_id' => 'Falta el club que cede al jugador.',
                ]);
            }
        });
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function fromClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'from_club_id');
    }

    public function toClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'to_club_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BudgetMovement::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function isProposal(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }

    public function isLoan(): bool
    {
        return in_array($this->type, self::FREE, true);
    }

    /**
     * Si la operación trae al jugador al club o se lo lleva. Lo decide el tipo
     * y no los extremos: con un club de fuera, uno de los dos extremos no es
     * una fila de `clubs` sino un nombre.
     */
    public function isIncoming(): bool
    {
        return in_array($this->type, self::INCOMING, true);
    }

    /**
     * Cómo se llama al jugador cuando todavía no tiene ficha.
     */
    public function playerName(): ?string
    {
        return $this->player?->name ?? $this->external_player;
    }

    /**
     * Cómo se llama esta operación desde el lado de un club concreto: el mismo
     * traspaso es el fichaje de uno y la salida del otro.
     */
    public function labelFor(?Club $club): string
    {
        if ($this->isLoan()) {
            return (int) $this->from_club_id === (int) $club?->getKey() ? 'Cesión' : 'Préstamo';
        }

        return (int) $this->from_club_id === (int) $club?->getKey() ? 'Venta' : 'Fichaje';
    }

    /**
     * El otro extremo de la operación, visto desde un club: el club de la liga
     * que está al otro lado, o el nombre de fuera.
     */
    public function counterpartFor(?Club $club): ?string
    {
        $isSeller = (int) $this->from_club_id === (int) $club?->getKey();

        return $isSeller
            ? ($this->toClub?->name ?? $this->external_club)
            : ($this->fromClub?->name ?? $this->external_club);
    }
}
