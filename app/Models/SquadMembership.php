<?php

namespace App\Models;

use Database\Factories\SquadMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La pertenencia de un jugador a la plantilla de una temporada: qué dorsal
 * llevó y a qué título estuvo. Una cesión es una pertenencia de tipo `loan`
 * cuyo club propietario (`Player::club`) no es el del equipo donde juega.
 */
#[Fillable(['team_id', 'player_id', 'shirt_number', 'type', 'is_captain'])]
class SquadMembership extends Model
{
    /** @use HasFactory<SquadMembershipFactory> */
    use HasFactory;

    public const TYPE_OWNED = 'owned';

    public const TYPE_LOAN = 'loan';

    /**
     * Vocabulario en PHP, no un enum de base de datos — mismo criterio que
     * `GameEvent::TYPES`.
     */
    public const TYPES = [
        self::TYPE_OWNED => 'Propiedad',
        self::TYPE_LOAN => 'Cesión',
    ];

    protected function casts(): array
    {
        return [
            'shirt_number' => 'integer',
            'is_captain' => 'boolean',
        ];
    }

    /**
     * Un único capitán por equipo y temporada: al marcar a uno, se desmarca a
     * quien lo era. Vive aquí y no en el formulario porque es un invariante del
     * dato, no un detalle de la pantalla que lo cambia.
     */
    protected static function booted(): void
    {
        static::saving(function (self $membership): void {
            if ($membership->is_captain) {
                static::query()
                    ->where('team_id', $membership->team_id)
                    ->when($membership->exists, fn ($query) => $query->whereKeyNot($membership->getKey()))
                    ->update(['is_captain' => false]);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
