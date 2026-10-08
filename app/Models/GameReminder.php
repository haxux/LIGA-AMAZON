<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Que ya se avisó de un partido con X días de antelación, o de que ya entró
 * en fecha. Ver `database/migrations/..._create_game_reminders_table.php`.
 */
#[Fillable(['game_id', 'kind', 'sent_at'])]
class GameReminder extends Model
{
    public const KIND_3_DAYS = 'days_3';

    public const KIND_2_DAYS = 'days_2';

    public const KIND_1_DAY = 'days_1';

    public const KIND_KICKOFF = 'kickoff';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
