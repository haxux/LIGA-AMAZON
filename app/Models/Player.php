<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'club_id', 'name', 'position', 'specific_position', 'market_value', 'birth_date',
    'photo_path', 'fc_id',
])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    public const POSITION_GOALKEEPER = 'Goalkeeper';

    /**
     * Vocabulary on the model rather than in a Filament form class, for the
     * same reason as GameEvent::TYPES: the clean-sheet guard below needs to
     * know what a goalkeeper is, and app/Models must not reach into
     * app/Filament to find out.
     *
     * @var array<int, string>
     */
    public const POSITIONS = [self::POSITION_GOALKEEPER, 'Defender', 'Midfielder', 'Forward'];

    /**
     * Posiciones específicas por posición general (Fase 10). La general dice a
     * qué se dedica el jugador; ésta, dónde juega exactamente.
     *
     * El reparto importa: agrupar por general es lo que hace la ficha pública,
     * y una específica que no case con su general —un portero de extremo—
     * rompería esa agrupación. `booted()` lo impide.
     *
     * @var array<string, array<int, string>>
     */
    public const SPECIFIC_POSITIONS_BY_POSITION = [
        self::POSITION_GOALKEEPER => ['POR'],
        'Defender' => ['DFC', 'DCI', 'DCD', 'LI', 'LD', 'CAI', 'CAD'],
        'Midfielder' => ['MCD', 'MDI', 'MDD', 'MC', 'MCI', 'MCID', 'MI', 'MD', 'MCO', 'MOI', 'MOD'],
        'Forward' => ['SD', 'SDI', 'SDD', 'EI', 'ED', 'DC', 'DI', 'DD'],
    ];

    /**
     * Las 27, en una sola lista.
     *
     * @var array<int, string>
     */
    public const SPECIFIC_POSITIONS = [
        'POR',
        'DFC', 'DCI', 'DCD', 'LI', 'LD', 'CAI', 'CAD',
        'MCD', 'MDI', 'MDD', 'MC', 'MCI', 'MCID', 'MI', 'MD', 'MCO', 'MOI', 'MOD',
        'SD', 'SDI', 'SDD', 'EI', 'ED', 'DC', 'DI', 'DD',
    ];

    /**
     * @return array<int, string>
     */
    public static function specificPositionsFor(?string $position): array
    {
        return self::SPECIFIC_POSITIONS_BY_POSITION[$position] ?? [];
    }

    /**
     * Invariante de entidad: la posición específica pertenece a la general.
     * Misma forma que los guards de `Game`, `Matchday`, `StandingZone` y
     * `User` — el formulario ya filtra las opciones, pero eso sólo cubre el
     * camino de la interfaz.
     */
    protected static function booted(): void
    {
        static::saving(function (Player $player): void {
            if ($player->specific_position === null) {
                return;
            }

            if (! in_array($player->specific_position, self::specificPositionsFor($player->position), true)) {
                throw ValidationException::withMessages([
                    'specific_position' => "La posición {$player->specific_position} no corresponde a un {$player->position}.",
                ]);
            }
        });

        // El valor no puede ser negativo. La columna es unsigned, pero SQLite
        // —el motor de la suite— no hace cumplir eso, así que el invariante se
        // dice aquí y vale en los dos motores.
        static::saving(function (Player $player): void {
            if ($player->market_value !== null && $player->market_value < 0) {
                throw ValidationException::withMessages([
                    'market_value' => 'El valor de un jugador no puede ser negativo.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            // La salida de la liga es una fecha, no una cadena: sin este cast
            // llegaba en crudo desde la base y cualquier ->format() sobre ella
            // reventaba la página que la leyera (Fase 12, D9).
            'left_at' => 'date',
            'shirt_number' => 'integer',
            'market_value' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * La ficha del catálogo de EA FC de la que salió este jugador, si salió de
     * alguna. Enlace y no copia: las medias viven en el catálogo, así que
     * corregir una vale para todos los que la compartan, y un jugador creado a
     * mano simplemente no tiene ninguna.
     *
     * Sin clave foránea en la base a propósito — el catálogo se vacía y se
     * reimporta entero con cada edición del juego, y eso no debe arrastrar a
     * nadie que esté jugando.
     */
    public function fcPlayer(): BelongsTo
    {
        return $this->belongsTo(FcPlayer::class, 'fc_id', 'fc_id');
    }

    /**
     * La plantilla de AHORA: sólo las pertenencias abiertas.
     *
     * Por omisión y no por casualidad. Desde que una salida cierra la fila en
     * vez de borrarla, «plantilla» y «quién pasó por la plantilla» son dos
     * preguntas distintas, y la que hace casi todo el mundo es la primera. La
     * otra se pide a propósito con `allMemberships()`, y así este cambio no
     * obliga a revisar los diecinueve sitios que leen plantillas.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(SquadMembership::class)->whereNull('left_at');
    }

    /**
     * Todas las que hubo, cerradas incluidas: quién estuvo en algún momento.
     * Es lo que necesita el formulario de eventos para dejar anotar un partido
     * anterior a un traspaso.
     */
    public function allMemberships(): HasMany
    {
        return $this->hasMany(SquadMembership::class);
    }

    /**
     * Lo que hizo en los partidos: goles, asistencias, tarjetas, porterías a
     * cero. Sobrevive a que se vaya de la liga —su ficha no se borra justo por
     * esto (diseño D9)—, y por eso el formulario de eventos pregunta por aquí
     * para dejar corregir un partido de alguien que ya no está.
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    /**
     * El equipo en el que juega esta temporada, si está inscrito en ella. No es
     * un accesor de compatibilidad: `Player::team()` desapareció en la Fase 9
     * porque su significado cambió — un jugador ya no pertenece a un equipo,
     * pertenece a un club y participa en plantillas.
     */
    public function teamIn(Season $season): ?Team
    {
        return $this->memberships()
            ->whereHas('team', fn ($query) => $query->where('season_id', $season->getKey()))
            ->with('team')
            ->first()?->team;
    }

    public function gameEvents(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }
}
