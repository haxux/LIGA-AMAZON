<?php

namespace App\Filament\Concerns;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Player;
use App\Models\User;
use App\Services\OfferService;
use App\Services\SeasonResolver;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El chat, compartido por los dos paneles (Fase 13).
 *
 * Es el mismo hilo visto desde `/club` y desde `/admin`, así que la lógica vive
 * aquí y cada panel monta una página de tres líneas. Duplicarla sería garantizar
 * que las dos copias se separen.
 *
 * Se refresca por sondeo (design D11): websockets exigirían un proceso
 * permanente que este despliegue, que escala a cero, no tiene.
 */
trait ChatScreen
{
    public ?int $conversationId = null;

    public string $body = '';

    public ?string $offerPlayerId = null;

    public ?string $offerAmount = null;

    public ?string $counterAmount = null;

    public ?int $counteringOfferId = null;

    public function conversation(): ?Conversation
    {
        if ($this->conversationId === null) {
            return null;
        }

        $conversation = Conversation::query()->with('participants')->find($this->conversationId);

        // Segunda cerradura: el hilo de otros no se abre ni tecleando su id.
        return $conversation?->includes($this->user()) ? $conversation : null;
    }

    /**
     * Los hilos abiertos, el más reciente primero, con lo que falta por leer.
     *
     * @return Collection<int, Conversation>
     */
    public function conversations(): Collection
    {
        return Conversation::query()
            ->whereHas('participants', fn (Builder $query) => $query->whereKey($this->user()->getKey()))
            ->with(['participants', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
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
        return User::query()
            ->whereKeyNot($this->user()->getKey())
            ->when($this->user()->isAdmin(), fn (Builder $query) => $query->where('role', User::ROLE_COACH))
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

    public function openWith(int $userId): void
    {
        $other = User::query()->find($userId);

        if ($other === null || $other->is($this->user())) {
            return;
        }

        $this->open(Conversation::between($this->user(), $other)->getKey());
    }

    public function open(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->reset(['body', 'offerPlayerId', 'offerAmount', 'counterAmount', 'counteringOfferId']);

        $this->conversation()?->markReadBy($this->user());
    }

    public function send(): void
    {
        $conversation = $this->conversation();

        if ($conversation === null || blank($this->body)) {
            return;
        }

        Message::create([
            'conversation_id' => $conversation->getKey(),
            'user_id' => $this->user()->getKey(),
            'body' => $this->body,
        ]);

        $this->body = '';
        $conversation->markReadBy($this->user());
    }

    /**
     * La plantilla del interlocutor, que es sobre la que se ofrece. Un técnico
     * no oferta por los suyos.
     *
     * @return array<int, string>
     */
    public function offerableOptions(): array
    {
        $conversation = $this->conversation();
        $otherClub = $conversation?->other($this->user())?->club;

        if (! $this->mayOffer() || $otherClub === null) {
            return [];
        }

        $season = app(SeasonResolver::class)->active();

        return Player::query()
            ->where('club_id', $otherClub->getKey())
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
    public function mayOffer(): bool
    {
        $conversation = $this->conversation();

        return $this->user()->isCoach()
            && $this->user()->club_id !== null
            && $conversation?->other($this->user())?->isCoach() === true;
    }

    public function sendOffer(): void
    {
        $conversation = $this->conversation();

        if ($conversation === null || ! $this->mayOffer() || blank($this->offerPlayerId) || blank($this->offerAmount)) {
            return;
        }

        try {
            Offer::create([
                'conversation_id' => $conversation->getKey(),
                'player_id' => (int) $this->offerPlayerId,
                'from_club_id' => $this->user()->club_id,
                'amount' => (int) $this->offerAmount,
                'status' => Offer::STATUS_SENT,
                'moved_by' => $this->user()->getKey(),
            ]);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title($exception->validator->errors()->first())->send();

            return;
        }

        $this->reset(['offerPlayerId', 'offerAmount']);
    }

    public function acceptOffer(int $offerId): void
    {
        $this->answer($offerId, fn (Offer $offer) => app(OfferService::class)->accept($offer, $this->user()));

        Notification::make()
            ->success()
            ->title('Oferta aceptada')
            ->body('El administrador la verá pendiente de ejecutar; el traspaso lo registra él.')
            ->send();
    }

    public function rejectOffer(int $offerId): void
    {
        $this->answer($offerId, fn (Offer $offer) => app(OfferService::class)->reject($offer, $this->user()));
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
            fn (Offer $offer) => app(OfferService::class)->counter($offer, $this->user(), $amount),
        );

        $this->reset(['counteringOfferId', 'counterAmount']);
    }

    public function mayAnswer(Offer $offer): bool
    {
        return app(OfferService::class)->mayAnswer($offer, $this->user());
    }

    public function unreadTotal(): int
    {
        return static::unreadCount();
    }

    /**
     * Para la insignia del menú, que se pinta sin que la página exista todavía:
     * de ahí que sea estática y no dependa del componente.
     */
    public static function unreadCount(): int
    {
        $user = auth()->user();

        if ($user === null) {
            return 0;
        }

        return Conversation::query()
            ->whereHas('participants', fn (Builder $query) => $query->whereKey($user->getKey()))
            ->with('participants')
            ->get()
            ->sum(fn (Conversation $conversation) => $conversation->unreadFor($user));
    }

    private function answer(int $offerId, callable $move): void
    {
        $offer = Offer::query()->with('conversation.participants')->find($offerId);

        if ($offer === null) {
            return;
        }

        try {
            $move($offer);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title($exception->validator->errors()->first())->send();
        }
    }

    private function user(): User
    {
        return auth()->user();
    }
}
