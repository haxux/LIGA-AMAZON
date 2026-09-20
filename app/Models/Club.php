<?php

namespace App\Models;

use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * La identidad permanente de un club, la que sobrevive a las temporadas. Su
 * participación en cada una de ellas es una fila de `teams`.
 */
#[Fillable(['name', 'short_name', 'crest_path', 'founded_year'])]
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function stadium(): HasOne
    {
        return $this->hasOne(Stadium::class);
    }

    public function trophies(): HasMany
    {
        return $this->hasMany(Trophy::class);
    }
}
