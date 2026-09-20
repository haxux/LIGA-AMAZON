<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['club_id', 'name', 'position', 'specific_position', 'birth_date'])]
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
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'shirt_number' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SquadMembership::class);
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
