<?php

namespace App\Models;

use App\Observers\MessageObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Un mensaje de texto del chat. Sin adjuntos (decisión cerrada).
 */
#[Fillable(['conversation_id', 'user_id', 'body'])]
#[ObservedBy(MessageObserver::class)]
class Message extends Model
{
    /**
     * Un mensaje de chat, no una carta. Sin tope, uno solo podía ocupar
     * megas, viajar entero en cada repintado de los dos y llegar recortado al
     * aviso push. Mismo criterio que `GameComment::MAX_LENGTH`.
     */
    public const MAX_LENGTH = 2000;

    protected static function booted(): void
    {
        static::saving(function (Message $message): void {
            if (blank($message->body)) {
                throw ValidationException::withMessages(['body' => 'Un mensaje vacío no se envía.']);
            }

            if (mb_strlen($message->body) > self::MAX_LENGTH) {
                throw ValidationException::withMessages(['body' => 'Un mensaje admite como mucho '.self::MAX_LENGTH.' caracteres.']);
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
