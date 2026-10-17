<?php

namespace App\Models;

use Database\Factories\GameCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Lo que un técnico dice de un partido, en el detalle público de éste.
 */
#[Fillable(['game_id', 'user_id', 'body'])]
class GameComment extends Model
{
    /** @use HasFactory<GameCommentFactory> */
    use HasFactory;

    public const MAX_LENGTH = 500;

    protected static function booted(): void
    {
        static::saving(function (GameComment $comment): void {
            if (blank(trim((string) $comment->body))) {
                throw ValidationException::withMessages(['body' => 'Un comentario no puede estar vacío.']);
            }

            if (mb_strlen($comment->body) > self::MAX_LENGTH) {
                throw ValidationException::withMessages(['body' => 'Un comentario admite como mucho '.self::MAX_LENGTH.' caracteres.']);
            }
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
