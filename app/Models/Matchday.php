<?php

namespace App\Models;

use Database\Factories\MatchdayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['season_id', 'division_id', 'number', 'date'])]
class Matchday extends Model
{
    /** @use HasFactory<MatchdayFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'date' => 'date',
        ];
    }

    /**
     * Entity invariant: the division a matchday sits in must belong to that
     * matchday's own season. Enforced here rather than in the DB (a composite
     * FK on (season_id, division_id) would need a redundant unique key on
     * divisions) for the same reason as Game's and Season's guards — every
     * write path goes through Eloquent. MatchdayForm already scopes the
     * division Select to the chosen season; this catches the stale-form and
     * programmatic-write cases the UI cannot.
     *
     * NOTE: DatabaseSeeder uses WithoutModelEvents, which mutes this guard
     * during seeding (see design D3/D9) — the seeder passes a division it
     * created on the same season by construction.
     */
    protected static function booted(): void
    {
        static::saving(function (Matchday $matchday): void {
            if (! $matchday->isDirty(['season_id', 'division_id'])) {
                return;
            }

            $divisionSeasonId = Division::query()->whereKey($matchday->division_id)->value('season_id');

            if ($divisionSeasonId === null) {
                return; // no such division — the FK/NOT NULL constraint speaks for itself
            }

            if ((int) $divisionSeasonId !== (int) $matchday->season_id) {
                throw ValidationException::withMessages([
                    'division_id' => 'The division must belong to the same season as the matchday.',
                ]);
            }
        });
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
