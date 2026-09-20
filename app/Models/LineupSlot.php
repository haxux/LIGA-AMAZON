<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lineup_id', 'player_id', 'slot'])]
class LineupSlot extends Model
{
    protected function casts(): array
    {
        return [
            'slot' => 'integer',
        ];
    }

    public function lineup(): BelongsTo
    {
        return $this->belongsTo(Lineup::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
