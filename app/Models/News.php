<?php

namespace App\Models;

use App\Observers\NewsObserver;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(NewsObserver::class)]
#[Fillable(['team_id', 'title', 'slug', 'body', 'cover_path', 'published_at'])]
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * A news item is publicly visible once its published_at has arrived —
     * null (draft) or a future timestamp are both hidden (design D-News).
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Publicadas y todavía sin anunciar por push.
     *
     * La marca es lo que lo hace ocurrir UNA vez: la anuncian dos caminos
     * —guardarla ya publicada, y el barrido del cron para las programadas, que
     * se publican solas al llegar su fecha— y sin ella editar el título de una
     * vieja volvería a avisar a todo el mundo.
     */
    #[Scope]
    protected function awaitingNotification(Builder $query): void
    {
        $query->published()->whereNull('notified_at');
    }
}
