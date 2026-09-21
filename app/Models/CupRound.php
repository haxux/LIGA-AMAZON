<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Una ronda del cuadro: octavos, cuartos, semifinal, final.
 *
 * Los partidos por eliminatoria se eligen aquí y no en la copa porque así se
 * juegan de verdad: una final a partido único con semifinales a ida y vuelta es
 * lo corriente (decisión del propietario).
 */
#[Fillable(['cup_id', 'name', 'position', 'legs'])]
class CupRound extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'legs' => 'integer',
        ];
    }

    /**
     * Invariante de entidad, del mismo estilo que los de `Game` y `Matchday`:
     * una eliminatoria se juega a uno o a dos partidos y no a tres.
     */
    protected static function booted(): void
    {
        static::saving(function (CupRound $round): void {
            if (! in_array($round->legs, [1, 2], true)) {
                throw ValidationException::withMessages([
                    'legs' => 'Una eliminatoria se juega a uno o a dos partidos.',
                ]);
            }
        });
    }

    public function cup(): BelongsTo
    {
        return $this->belongsTo(Cup::class);
    }

    public function ties(): HasMany
    {
        return $this->hasMany(CupTie::class);
    }

    public function isTwoLegged(): bool
    {
        return $this->legs === 2;
    }
}
