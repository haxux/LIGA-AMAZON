<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['club_id', 'name', 'position', 'birth_date'])]
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
