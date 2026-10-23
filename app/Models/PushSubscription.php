<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una suscripción push de un navegador. Ver la migración para el porqué de
 * `topics` y de que `user_id` sólo se rellene para el tema "chat".
 */
#[Fillable(['endpoint', 'public_key', 'auth_token', 'topics', 'user_id'])]
class PushSubscription extends Model
{
    public const TOPIC_MATCHES = 'matches';

    public const TOPIC_CHAT = 'chat';

    /**
     * Las noticias de la liga. Como los partidos, no pide sesión: a una
     * noticia la lee cualquiera, así que avisar de ella tampoco debería exigir
     * entrar (decisión del propietario, 2026-10-13).
     */
    public const TOPIC_NEWS = 'news';

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

    /**
     * A quién avisa del chat este navegador: las cuentas que tenía abiertas
     * al apuntarse (ver la migración de `push_subscription_user`).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function hasTopic(string $topic): bool
    {
        return in_array($topic, $this->topics ?? [], true);
    }
}
