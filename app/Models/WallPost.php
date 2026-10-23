<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una publicación de «A Segunda», el foro interno (ver la migración). Una
 * respuesta es una publicación con `parent_id`.
 */
#[Fillable(['user_id', 'parent_id', 'body'])]
class WallPost extends Model
{
    public const MAX_BODY = 500;

    public const MAX_IMAGES = 4;

    /**
     * Las fotos se borran del disco con la publicación: las filas se van en
     * cascada, pero los ficheros no saben de cascadas. También las de sus
     * respuestas, que se van con ella.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $post): void {
            $post->replies()->get()->each->delete();
            $post->images()->get()->each->delete();
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function images(): HasMany
    {
        return $this->hasMany(WallImage::class)->orderBy('position');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(WallReaction::class);
    }

    /**
     * Lo que hace falta para pintar una publicación a quien la mira: autor
     * con su club, fotos, cuántas respuestas, cuántos «me gusta» y «no me
     * gusta», y cómo reaccionó él.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forReader(Builder $query, User $reader): void
    {
        $query->with(['author.club', 'images', 'reactions' => fn ($reactions) => $reactions->where('user_id', $reader->getKey())])
            ->withCount([
                'replies',
                'reactions as likes_count' => fn ($reactions) => $reactions->where('value', 1),
                'reactions as dislikes_count' => fn ($reactions) => $reactions->where('value', -1),
            ]);
    }

    /** Cómo reaccionó quien mira (cargado por `forReader`): 1, -1 o 0. */
    public function myReaction(): int
    {
        return (int) ($this->reactions->first()?->value ?? 0);
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }
}
