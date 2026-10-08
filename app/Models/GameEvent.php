<?php

namespace App\Models;

use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[Fillable(['game_id', 'player_id', 'type', 'minute', 'related_event_id'])]
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

    /**
     * El gol al que pertenece esta asistencia, cuando lo es.
     */
    public function relatedEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_event_id');
    }

    /**
     * La asistencia de este gol, si el operador la registró. Nace junto al
     * gol desde el formulario (GameEventsRelationManager) en lugar de como un
     * evento suelto, para poder mostrarla debajo de él.
     */
    public function assist(): HasOne
    {
        return $this->hasOne(self::class, 'related_event_id')->where('type', self::TYPE_ASSIST);
    }

    /**
     * Si esta amarilla es la SEGUNDA de su jugador en este partido, y por tanto
     * una expulsión.
     *
     * Se deduce en vez de guardarse (decisión del propietario): el operador
     * carga las dos amarillas, que es lo que pasó, y la expulsión sale de ahí.
     * Un tipo de evento nuevo habría que elegirlo a mano y se puede olvidar; dos
     * amarillas del mismo jugador en el mismo partido no significan otra cosa.
     *
     * «Segunda» por orden de carga, que es el orden en que se leen los eventos
     * desde que el minuto dejó de registrarse: la de id mayor es la posterior.
     */
    public function isSecondYellow(): bool
    {
        if ($this->type !== self::TYPE_YELLOW_CARD) {
            return false;
        }

        return self::query()
            ->where('game_id', $this->game_id)
            ->where('player_id', $this->player_id)
            ->where('type', self::TYPE_YELLOW_CARD)
            ->whereKeyNot($this->getKey())
            ->where('id', '<', $this->getKey())
            ->exists();
    }
}
