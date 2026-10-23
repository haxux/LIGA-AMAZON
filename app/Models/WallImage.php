<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Una foto de una publicación de «A Segunda», ya reducida y sin sus datos
 * ocultos (ver WallService::storeImage).
 */
#[Fillable(['wall_post_id', 'path', 'width', 'height', 'position'])]
class WallImage extends Model
{
    protected static function booted(): void
    {
        // El fichero, con la fila.
        static::deleted(fn (self $image) => Storage::disk(config('filesystems.uploads'))->delete($image->path));
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(WallPost::class, 'wall_post_id');
    }

    public function url(): string
    {
        return Storage::disk(config('filesystems.uploads'))->url($this->path);
    }
}
