<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['matchday_id', 'home_team_id', 'away_team_id', 'kickoff_at', 'home_score', 'away_score'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
        ];
    }

    /**
     * Entity invariant: a team cannot play against itself. Enforced here
     * (not a raw SQL CHECK, see design D2) because every write path goes
     * through Eloquent. The (int) cast is load-bearing: form input arrives
     * as strings, so a naive === would miss e.g. 3 vs '3'.
     *
     * NOTE: DatabaseSeeder uses WithoutModelEvents, which mutes this guard
     * during seeding (see design D3) — the seeder guarantees distinct teams
     * by construction (circle-method round-robin), not by this guard.
     */
    protected static function booted(): void
    {
        static::saving(function (Game $game): void {
            if ($game->away_team_id !== null && (int) $game->home_team_id === (int) $game->away_team_id) {
                throw ValidationException::withMessages([
                    'away_team_id' => 'A team cannot play against itself.',
                ]);
            }
        });
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }
}
