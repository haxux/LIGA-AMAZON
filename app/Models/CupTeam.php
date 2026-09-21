<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La inscripción de un equipo en una copa, con su grupo si la copa lo tiene.
 *
 * El equipo es el de la temporada (`teams`), no el club: una copa se juega en
 * una temporada concreta, como todo lo demás.
 */
#[Fillable(['cup_id', 'team_id', 'cup_group_id'])]
class CupTeam extends Model
{
    public function cup(): BelongsTo
    {
        return $this->belongsTo(Cup::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CupGroup::class, 'cup_group_id');
    }
}
