<?php

namespace App\Models;

use Database\Factories\TrophyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un título ganado por un club en una temporada. Lo crea el administrador; el
 * técnico y el público lo leen.
 */
#[Fillable(['club_id', 'season_id', 'name'])]
class Trophy extends Model
{
    /** @use HasFactory<TrophyFactory> */
    use HasFactory;

    protected $table = 'trophies';

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
