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
#[Fillable(['team_id', 'player_id', 'shirt_number', 'type'])]
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
        ];
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
