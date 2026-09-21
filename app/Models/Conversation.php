<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Un hilo entre dos personas (Fase 13).
 *
 * Hay UNA conversación por pareja: `between()` la busca antes de crearla. Dos
 * hilos entre los mismos dos partirían la historia y dejarían el contador de no
 * leídos sin significado.
 */
class Conversation extends Model
{
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_at')->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * La conversación entre dos personas, creándola si es la primera vez.
     */
    public static function between(User $one, User $other): self
    {
        $conversation = self::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($one->getKey()))
            ->whereHas('participants', fn ($query) => $query->whereKey($other->getKey()))
            ->first();

        if ($conversation !== null) {
            return $conversation;
        }

        $conversation = self::create();
        $conversation->participants()->attach([$one->getKey(), $other->getKey()]);

        return $conversation;
    }

    public function other(User $user): ?User
    {
        return $this->participants->firstWhere(fn (User $participant) => ! $participant->is($user));
    }

    public function includes(User $user): bool
    {
        return $this->participants->contains(fn (User $participant) => $participant->is($user));
    }

    /**
     * Lo que a alguien le falta por leer: los mensajes de la otra persona
     * posteriores a la última vez que abrió el hilo.
     */
    public function unreadFor(User $user): int
    {
        $lastRead = $this->participants->firstWhere(fn (User $participant) => $participant->is($user))?->pivot?->last_read_at;

        return $this->messages()
            ->where('user_id', '!=', $user->getKey())
            ->when($lastRead, fn ($query) => $query->where('created_at', '>', $lastRead))
            ->count();
    }

    /**
     * Lo que le falta por leer a alguien en TODOS sus hilos. Vive aquí y no en
     * la pantalla del chat porque la cabecera del sitio también lo pinta, y dos
     * cuentas distintas del mismo número acabarían discrepando.
     */
    public static function unreadTotalFor(User $user): int
    {
        return self::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($user->getKey()))
            ->with('participants')
            ->get()
            ->sum(fn (self $conversation) => $conversation->unreadFor($user));
    }

    public function markReadBy(User $user): void
    {
        $this->participants()->updateExistingPivot($user->getKey(), ['last_read_at' => now()]);
    }

    /**
     * El hilo entero —mensajes y ofertas— en el orden en que ocurrió. Una
     * oferta se lee entre los mensajes porque ahí es donde se hizo.
     *
     * Se ordena con UNA clave compuesta y no con dos criterios. En el multiorden
     * de `Collection` (`sortBy([...])`) una función no es un extractor de clave
     * sino un COMPARADOR: recibe los dos elementos y tiene que devolver un
     * entero —`Collection::sortByMany()`, `$result = $prop($a, $b)`—. Pasarle
     * `fn ($entry) => $entry->created_at` devolvía un Carbon como resultado de
     * la comparación y reventaba el hilo entero con "Object of class
     * Illuminate\Support\Carbon could not be converted to int".
     *
     * @return Collection<int, Message|Offer>
     */
    public function timeline(): Collection
    {
        return $this->messages()->with('author')->get()
            ->concat($this->offers()->with(['player', 'fromClub', 'mover'])->get())
            // El id sólo desempata dentro del mismo segundo, y ahí el orden
            // entre un mensaje y una oferta da igual: pasaron a la vez.
            ->sortBy(fn ($entry) => [$entry->created_at?->getTimestamp() ?? 0, $entry->getKey()])
            ->values();
    }
}
