<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\User;
use App\Services\OfferService;
use App\Services\SeasonResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * El chat, en el sitio público (corrección posterior a la Fase 13).
 *
 * Estaba montado dentro de los dos paneles, y allí una conversación compite con
 * el menú lateral y la cabecera de Filament por un carril estrecho. Aquí tiene
 * la página entera, que es lo que una mensajería necesita.
 *
 * Vive en un único sitio, `/chat`, para técnicos y presidentes: dos pantallas
 * para el mismo hilo acaban separándose, y el contador de no leídos dejaría de
 * significar una sola cosa.
 */
class Chat extends Component
{
    public ?int $conversationId = null;

    public string $body = '';

    public string $offerKind = Offer::KIND_BUY;

    public ?string $offerPlayerId = null;

    public ?string $offerTerm = null;

    public ?string $offerAmount = null;

    public ?string $counterAmount = null;

    public ?int $counteringOfferId = null;

    public ?string $error = null;

    /**
     * Quien está mirando, venga por donde venga: el técnico entra por el guard
     * `club` y el presidente por `web`, y esta pantalla es de los dos. Con las
     * dos sesiones abiertas manda la del técnico, que es la que el sitio usa
     * para todo lo demás (el escudo de la cabecera, su club, su once).
     */
    public function user(): ?User
    {
        return auth('club')->user() ?? auth()->user();
    }

    public function conversation(): ?Conversation
    {
        $user = $this->user();

        if ($this->conversationId === null || $user === null) {
            return null;
        }

        $conversation = Conversation::query()->with('participants')->find($this->conversationId);

        // Segunda cerradura: el hilo de otros no se abre ni tecleando su id.
        return $conversation?->includes($user) ? $conversation : null;
    }

    /**
     * Los hilos abiertos, el más reciente primero.
     *
     * @return Collection<int, Conversation>
     */
    public function conversations(): Collection
    {
        $user = $this->user();

        if ($user === null) {
            return collect();
        }

        return Conversation::query()
            ->whereHas('participants', fn (Builder $query) => $query->whereKey($user->getKey()))
            ->with(['participants.club', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => $conversation->messages->first()?->created_at ?? $conversation->created_at)
            ->values();
    }

    /**
     * Con quién se puede hablar: un técnico habla con los demás técnicos y con
     * los presidentes; un presidente, con los técnicos. Entre presidentes no
     * hace falta un chat: comparten panel.
     *
     * @return Collection<int, User>
     */
    public function contacts(): Collection
    {
        $user = $this->user();

        if ($user === null) {
            return collect();
        }

        return User::query()
            ->whereKeyNot($user->getKey())
            ->when($user->isAdmin(), fn (Builder $query) => $query->where('role', User::ROLE_COACH))
            ->with('club')
            ->orderBy('name')
            ->get();
    }

    /**
     * Los administradores son PRESIDENTES en el chat (decisión del propietario);
     * un técnico se presenta con su club, que es lo que le identifica.
     */
    public function displayName(?User $user): string
    {
        if ($user === null) {
            return 'Desconocido';
        }

        return $user->isAdmin()
            ? $user->name.' · Presidente'
            : $user->name.($user->club ? ' · '.$user->club->name : '');
    }

    public function subtitleFor(?User $user): ?string
    {
        return $user?->isAdmin() ? 'Presidente' : $user?->club?->name;
    }

    public function openWith(int $userId): void
    {
        $me = $this->user();
        $other = User::query()->find($userId);

        if ($me === null || $other === null || $other->is($me)) {
            return;
        }

        $this->open(Conversation::between($me, $other)->getKey());
    }

    public function open(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->reset(['body', 'offerPlayerId', 'offerAmount', 'counterAmount', 'counteringOfferId', 'error']);

        $user = $this->user();
        $conversation = $this->conversation();

        if ($user !== null && $conversation !== null) {
            $conversation->markReadBy($user);
        }
    }

    public function close(): void
    {
        $this->conversationId = null;
    }

    public function send(): void
    {
        $user = $this->user();
        $conversation = $this->conversation();

        if ($user === null || $conversation === null || blank($this->body)) {
            return;
        }

        Message::create([
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'body' => $this->body,
        ]);

        $this->body = '';
        $conversation->markReadBy($user);
    }

    /**
     * La plantilla del interlocutor, que es sobre la que se ofrece. Un técnico
     * no oferta por los suyos.
     *
     * @return array<int, string>
     */
    public function offerableOptions(): array
    {
        $user = $this->user();
        $otherClub = $this->conversation()?->other($user)?->club;

        if (! $this->mayOffer() || $otherClub === null) {
            return [];
        }

        // Pedir es pedir lo del otro; ofrecer es ofrecer lo propio.
        $club = $this->asking() ? $otherClub->getKey() : $user->club_id;

        $season = app(SeasonResolver::class)->active();

        return Player::query()
            ->where('club_id', $club)
            ->when($season, fn (Builder $query) => $query->whereHas(
                'memberships',
                fn (Builder $memberships) => $memberships->whereHas(
                    'team',
                    fn (Builder $team) => $team->where('season_id', $season->getKey()),
                ),
            ))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Ofrecer exige un club que compre, y un presidente no dirige ninguno
     * (invariante de `User` desde la Fase 9). Con él se habla; no se negocia.
     */
    /**
     * Si la operación elegida pide un jugador al interlocutor, en vez de
     * ofrecerle uno propio.
     */
    public function asking(): bool
    {
        return in_array($this->offerKind, Offer::ASKING, true);
    }

    public function free(): bool
    {
        return in_array($this->offerKind, Offer::FREE, true);
    }

    /**
     * @return array<string, string>
     */
    public function offerKinds(): array
    {
        return Offer::KINDS;
    }

    /**
     * Los plazos de una cesión son los mismos que los de un traspaso: no hay
     * dos vocabularios para lo mismo.
     *
     * @return array<string, string>
     */
    public function loanTerms(): array
    {
        return Transfer::LOAN_TERMS;
    }

    public function updatedOfferKind(): void
    {
        $this->reset(['offerPlayerId', 'offerAmount', 'offerTerm', 'error']);
    }

    public function mayOffer(): bool
    {
        $user = $this->user();

        return $user?->isCoach() === true
            && $user->club_id !== null
            && $this->conversation()?->other($user)?->isCoach() === true;
    }

    public function sendOffer(): void
    {
        $user = $this->user();
        $conversation = $this->conversation();

        if ($user === null || $conversation === null || ! $this->mayOffer()) {
            return;
        }

        if (blank($this->offerPlayerId)) {
            return;
        }

        // Una cesión se pacta por plazo; lo demás, por dinero.
        if ($this->free() ? blank($this->offerTerm) : blank($this->offerAmount)) {
            return;
        }

        try {
            Offer::create([
                'conversation_id' => $conversation->getKey(),
                'player_id' => (int) $this->offerPlayerId,
                'kind' => $this->offerKind,
                'from_club_id' => $user->club_id,
                'amount' => $this->free() ? 0 : (int) $this->offerAmount,
                'loan_term' => $this->free() ? $this->offerTerm : null,
                'status' => Offer::STATUS_SENT,
                'moved_by' => $user->getKey(),
            ]);
        } catch (ValidationException $exception) {
            $this->error = $exception->validator->errors()->first();

            return;
        }

        $this->reset(['offerPlayerId', 'offerAmount', 'offerTerm', 'error']);
    }

    public function acceptOffer(int $offerId): void
    {
        $this->answer($offerId, fn (Offer $offer, User $user) => app(OfferService::class)->accept($offer, $user));
    }

    public function rejectOffer(int $offerId): void
    {
        $this->answer($offerId, fn (Offer $offer, User $user) => app(OfferService::class)->reject($offer, $user));
    }

    public function startCounter(int $offerId): void
    {
        $this->counteringOfferId = $offerId;
        $this->counterAmount = null;
    }

    public function sendCounter(): void
    {
        if ($this->counteringOfferId === null || blank($this->counterAmount)) {
            return;
        }

        $amount = (int) $this->counterAmount;

        $this->answer(
            $this->counteringOfferId,
            fn (Offer $offer, User $user) => app(OfferService::class)->counter($offer, $user, $amount),
        );

        $this->reset(['counteringOfferId', 'counterAmount']);
    }

    public function mayAnswer(Offer $offer): bool
    {
        $user = $this->user();

        return $user !== null && app(OfferService::class)->mayAnswer($offer, $user);
    }

    public function unreadTotal(): int
    {
        $user = $this->user();

        return $user === null ? 0 : Conversation::unreadTotalFor($user);
    }

    public function render()
    {
        return view('livewire.chat');
    }

    private function answer(int $offerId, callable $move): void
    {
        $user = $this->user();
        $offer = Offer::query()->with('conversation.participants')->find($offerId);

        if ($user === null || $offer === null) {
            return;
        }

        try {
            $move($offer, $user);
            $this->error = null;
        } catch (ValidationException $exception) {
            $this->error = $exception->validator->errors()->first();
        }
    }
}
