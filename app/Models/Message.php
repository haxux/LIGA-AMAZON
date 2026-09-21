<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Un mensaje de texto del chat. Sin adjuntos (decisión cerrada).
 */
#[Fillable(['conversation_id', 'user_id', 'body'])]
class Message extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Message $message): void {
            if (blank($message->body)) {
                throw ValidationException::withMessages(['body' => 'Un mensaje vacío no se envía.']);
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
