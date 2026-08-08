<?php

namespace App\Models;

use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'player_id', 'type', 'minute'])]
class GameEvent extends Model
{
    /** @use HasFactory<GameEventFactory> */
    use HasFactory;

    public const TYPE_GOAL = 'goal';

    public const TYPE_ASSIST = 'assist';

    /**
     * PHP-level vocabulary, not a DB enum (design D1/D4) — lives on the
     * model, not a Filament form class, so app/Services/ never depends on
     * app/Filament/.
     */
    public const TYPES = [
        self::TYPE_GOAL => 'Goal',
        self::TYPE_ASSIST => 'Assist',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
