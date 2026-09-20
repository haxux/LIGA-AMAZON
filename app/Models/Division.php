<?php

namespace App\Models;

use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['season_id', 'name'])]
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use HasFactory;

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function matchdays(): HasMany
    {
        return $this->hasMany(Matchday::class);
    }

    /**
     * Ordered on the relationship: every consumer — the panel's relation
     * manager and the public legend alike — wants them top of the table
     * downwards, and the bands cannot overlap, so the order is total.
     */
    public function standingZones(): HasMany
    {
        return $this->hasMany(StandingZone::class)->orderBy('from_position');
    }
}
