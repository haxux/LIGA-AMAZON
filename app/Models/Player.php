<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'name', 'position', 'birth_date', 'shirt_number'])]
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function gameEvents(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }
}
