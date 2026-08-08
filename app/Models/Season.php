<?php

namespace App\Models;

use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'start_date', 'end_date', 'is_current'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Entity invariant: at most one season is flagged current. Enforced here
     * (not a partial unique index) for the same reason as Game's guard —
     * every write path goes through Eloquent, and a partial index would
     * reintroduce the SQLite-vs-MySQL divergence Fase 2/D5 already hit.
     *
     * This guard RESOLVES rather than REJECTS: marking a season current
     * silently unsets every other. The admin never sees a validation error
     * (design D8).
     *
     * NOTE: DatabaseSeeder uses WithoutModelEvents, which mutes this guard
     * during seeding (see design D9) — the seeder sets is_current directly
     * on the single season it creates.
     */
    protected static function booted(): void
    {
        static::saving(function (Season $season): void {
            if (! $season->is_current) {
                return;
            }

            static::query()
                ->when($season->exists, fn (Builder $query) => $query->whereKeyNot($season->getKey()))
                ->where('is_current', true)
                ->update(['is_current' => false]);
        });
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function matchdays(): HasMany
    {
        return $this->hasMany(Matchday::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }
}
