<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una suscripción push de un navegador. Ver la migración para el porqué de
 * `topics` y de que `user_id` sólo se rellene para el tema "chat".
 */
#[Fillable(['endpoint', 'public_key', 'auth_token', 'topics', 'user_id'])]
class PushSubscription extends Model
{
    public const TOPIC_MATCHES = 'matches';

    public const TOPIC_CHAT = 'chat';

    protected function casts(): array
    {
        return [
            'topics' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasTopic(string $topic): bool
    {
        return in_array($topic, $this->topics ?? [], true);
    }
}
