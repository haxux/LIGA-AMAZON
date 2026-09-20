<?php

namespace App\Models;

use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['game_id', 'player_id', 'type', 'minute'])]
class GameEvent extends Model
{
    /** @use HasFactory<GameEventFactory> */
    use HasFactory;

    public const TYPE_GOAL = 'goal';

    public const TYPE_ASSIST = 'assist';

    public const TYPE_YELLOW_CARD = 'yellow_card';

    public const TYPE_RED_CARD = 'red_card';

    public const TYPE_CLEAN_SHEET = 'clean_sheet';

    /**
     * PHP-level vocabulary, not a DB enum (design D1/D4) — lives on the
     * model, not a Filament form class, so app/Services/ never depends on
     * app/Filament/. `type` is a plain string column, so widening this list
     * needs no migration.
     */
    public const TYPES = [
        self::TYPE_GOAL => 'Goal',
        self::TYPE_ASSIST => 'Assist',
        self::TYPE_YELLOW_CARD => 'Yellow card',
        self::TYPE_RED_CARD => 'Red card',
        self::TYPE_CLEAN_SHEET => 'Clean sheet',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
        ];
    }

    /**
     * Entity invariant: a clean sheet belongs to a goalkeeper. Enforced here
     * rather than in the form alone, like Game's and Matchday's guards — the
     * relation manager scopes its player Select, but that covers only the UI
     * path. Minutes carry no meaning for a clean sheet either, so the guard
     * drops any that arrives.
     *
     * NOTE: WithoutModelEvents mutes this during seeding (design D3/D9).
     */
    protected static function booted(): void
    {
        static::saving(function (GameEvent $event): void {
            if ($event->type !== self::TYPE_CLEAN_SHEET) {
                return;
            }

            $event->minute = null;

            $position = Player::query()->whereKey($event->player_id)->value('position');

            if ($position !== null && $position !== Player::POSITION_GOALKEEPER) {
                throw ValidationException::withMessages([
                    'player_id' => 'A clean sheet can only be recorded for a goalkeeper.',
                ]);
            }
        });
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
