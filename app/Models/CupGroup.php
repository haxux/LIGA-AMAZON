<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un grupo de la primera fase de una copa: una liga pequeña con su propia
 * tabla, que se deriva con el mismo código que la de una división.
 */
#[Fillable(['cup_id', 'name'])]
class CupGroup extends Model
{
    public function cup(): BelongsTo
    {
        return $this->belongsTo(Cup::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CupTeam::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
