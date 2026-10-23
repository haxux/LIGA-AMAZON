<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\FcPlayer;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\User;
use App\Services\OfferService;
use App\Services\SeasonResolver;
use App\Support\SiteAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
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

    public ?string $counterTerm = null;

    /** El jugador del catálogo elegido para pedírselo al presidente. */
    public ?int $offerFcId = null;

    public ?string $offerFcName = null;

    public string $catalogSearch = '';

    public ?int $counteringOfferId = null;

    public ?string $error = null;

    /**
     * Cuántas entradas del hilo abierto se pintan, contando desde la última.
     * Empieza en {@see self::HISTORY_PAGE} y crece de tanto en tanto con «Ver
     * anteriores»; abrir otro hilo lo devuelve al principio. Bloqueada: sólo
     * la mueve el servidor, que un navegador no pida el hilo entero de golpe.
     */
    #[Locked]
    public int $historyLimit = self::HISTORY_PAGE;

    /**
     * El tramo de historial que se pinta de una vez. Cada repintado (cada 5
     * segundos) viaja entero al navegador, así que un hilo de una temporada
     * completa no puede ir todo en cada uno.
     */
    public const HISTORY_PAGE = 50;

    /**
     * Lo ya calculado en ESTA petición. Una pantalla de chat se repinta cada 5
     * segundos y la vista pregunta lo mismo muchas veces —el hilo abierto, la
     * lista, quién puede ofertar—; sin esto cada pregunta era una consulta, y
     * un hilo de cuarenta mensajes se repintaba con más de trescientas. Eso es
     * lo que hacía que la pantalla se quedara pensando justo cuando se
     * mandaba algo. No es público: no viaja al navegador, y cada petición
     * empieza vacío.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * @template T
     *
     * @param  callable(): T  $compute
     * @return T
     */
    private function once(string $key, callable $compute): mixed
    {
        return $this->memo[$key] ??= $compute();
    }

    /**
     * Quien está mirando, venga por donde venga: el técnico entra por el guard
     * `club` y el presidente por `web`, y esta pantalla es de los dos. Con las
     * dos sesiones abiertas manda la del técnico, que es la que el sitio usa
     * para todo lo demás (el escudo de la cabecera, su club, su once).
     */
    public function user(): ?User
    {
        return app(SiteAccount::class)->active();
    }

    public function conversation(): ?Conversation
    {
        $user = $this->user();

        if ($this->conversationId === null || $user === null) {
            return null;
        }

        // Con el id en la clave: al abrir otro hilo dentro de la misma
        // petición no se devuelve el anterior.
        return $this->once('conversation:'.$this->conversationId, function () use ($user): ?Conversation {
            $conversation = Conversation::query()->with('participants.club')->find($this->conversationId);

            // Segunda cerradura: el hilo de otros no se abre ni tecleando su id.
            return $conversation?->includes($user) ? $conversation : null;
        });
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

        return $this->once('conversations', fn () => Conversation::query()
            ->whereHas('participants', fn (Builder $query) => $query->whereKey($user->getKey()))
            ->with(['participants.club', 'latestMessage'])
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => $conversation->latestMessage?->created_at ?? $conversation->created_at)
            ->values());
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

        return $this->once('contacts', fn () => User::query()
            ->whereKeyNot($user->getKey())
            ->when($user->isAdmin(), fn (Builder $query) => $query->where('role', User::ROLE_COACH))
            ->with('club')
            ->orderBy('name')
            ->get());
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
        $this->reset(['body', 'offerPlayerId', 'offerAmount', 'counterAmount', 'counteringOfferId', 'error', 'historyLimit']);

        $user = $this->user();
        $conversation = $this->conversation();

        if ($user !== null && $conversation !== null) {
            $conversation->markReadBy($user);
        }
    }

    /**
     * Lo último del hilo abierto, hasta `$historyLimit` entradas.
     *
     * Se pide UNA de más para saber si queda algo por encima sin contar el hilo
     * entero: si llega, hay anteriores, y esa sobrante no se pinta.
     *
     * @return array{entries: Collection<int, Message|Offer>, earlier: bool}
     */
    public function timeline(): array
    {
        $conversation = $this->conversation();

        if ($conversation === null) {
            return ['entries' => collect(), 'earlier' => false];
        }

        return $this->once('timeline:'.$conversation->getKey(), function () use ($conversation): array {
            $entries = $conversation->timeline($this->historyLimit + 1);
            $earlier = $entries->count() > $this->historyLimit;

            return [
                'entries' => $earlier ? $entries->slice(1)->values() : $entries,
                'earlier' => $earlier,
            ];
        });
    }

    public function showEarlier(): void
    {
        $this->historyLimit += self::HISTORY_PAGE;
    }

    /**
     * Lo que le falta por leer, hilo por hilo, para la lista: una consulta para
     * todos en vez de una por hilo.
     *
     * @return array<int, int>
     */
    public function unreadCounts(): array
    {
        $user = $this->user();

        return $user === null ? [] : $this->once('unread', fn () => Conversation::unreadCountsFor($user));
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

        if (mb_strlen($this->body) > Message::MAX_LENGTH) {
            $this->error = 'Un mensaje admite como mucho '.Message::MAX_LENGTH.' caracteres.';

            return;
        }

        if (! $this->withinSendingPace($user)) {
            return;
        }

        Message::create([
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'body' => $this->body,
        ]);

        $this->body = '';
        $this->error = null;
        $conversation->markReadBy($user);

        $this->dispatch('chat-sent');
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
        $other = $this->conversation()?->other($user);

        if (! $this->mayOffer() || $other === null) {
            return [];
        }

        // Al presidente no se le pide a nadie de la plantilla: lo que se pide
        // sale del catálogo (ver `catalogResults()`).
        if ($this->toPresident() && $this->asking()) {
            return [];
        }

        // Pedir es pedir lo del otro; ofrecer es ofrecer lo propio.
        $club = $this->asking() ? $other->club_id : $user->club_id;

        $season = app(SeasonResolver::class)->active();

        return array_map(fn (array $player) => $player['name'], $this->offerablePlayers($club, $season));
    }

    /**
     * El valor de mercado de cada jugador ofertable, por id. Va con el
     * formulario para que el importe se rellene en el navegador al elegir
     * jugador, sin ir al servidor: el desplegable no viaja hasta la siguiente
     * petición, y con eso el valor tardaba hasta el siguiente sondeo (5 s) en
     * aparecer.
     *
     * @return array<int, int|null>
     */
    public function offerableValues(): array
    {
        $user = $this->user();
        $other = $this->conversation()?->other($user);

        if (! $this->mayOffer() || $other === null || ($this->toPresident() && $this->asking())) {
            return [];
        }

        $club = $this->asking() ? $other->club_id : $user->club_id;

        return array_map(
            fn (array $player) => $player['value'],
            $this->offerablePlayers($club, app(SeasonResolver::class)->active()),
        );
    }

    /**
     * @return array<int, array{name: string, value: int|null}>
     */
    private function offerablePlayers(?int $club, mixed $season): array
    {
        return $this->once('options:'.$club, fn () => $this->playersOf($club, $season));
    }

    /**
     * @return array<int, array{name: string, value: int|null}>
     */
    private function playersOf(?int $club, mixed $season): array
    {
        $players = Player::query()
            ->where('club_id', $club)
            ->whereNull('left_at')
            ->when($season, fn (Builder $query) => $query->whereHas(
                'memberships',
                fn (Builder $memberships) => $memberships->whereHas(
                    'team',
                    fn (Builder $team) => $team->where('season_id', $season->getKey()),
                ),
            ))
            ->orderBy('name')
            ->get();

        // Un cedido no se negocia: ni se compra ni se vende hasta que acabe la
        // cesión. Se pregunta de una vez por todos y no jugador a jugador.
        $loaned = Player::loanedAmong($players->modelKeys());

        return $players
            ->reject(fn (Player $player) => in_array($player->getKey(), $loaned, true))
            ->mapWithKeys(fn (Player $player) => [$player->getKey() => [
                'name' => $player->name,
                'value' => $player->market_value === null ? null : (int) $player->market_value,
            ]])
            ->all();
    }

    /**
     * Si el hilo abierto es con el presidente, que no dirige ningún club.
     */
    public function toPresident(): bool
    {
        return $this->conversation()?->other($this->user())?->isAdmin() === true;
    }

    /**
     * Los jugadores del catálogo de EA FC que se pueden pedir al presidente:
     * los que coinciden con lo escrito y no juegan ya en un club de la liga.
     *
     * @return Collection<int, FcPlayer>
     */
    public function catalogResults(): Collection
    {
        if (! $this->toPresident() || ! $this->asking() || mb_strlen(trim($this->catalogSearch)) < 2) {
            return collect();
        }

        $inLeague = Player::query()->whereNotNull('fc_id')->whereNull('left_at')->pluck('fc_id');

        return FcPlayer::buscar($this->catalogSearch)
            ->whereNotIn('fc_id', $inLeague)
            ->limit(8)
            ->get();
    }

    public function selectCatalog(int $fcId): void
    {
        $ficha = FcPlayer::query()->where('fc_id', $fcId)->first();

        if ($ficha === null) {
            return;
        }

        $this->offerFcId = $fcId;
        $this->offerFcName = $ficha->nombre();
        $this->catalogSearch = '';
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
        return $this->toPresident() ? Offer::KINDS_PRESIDENT : Offer::KINDS;
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
        $this->reset(['offerPlayerId', 'offerAmount', 'offerTerm', 'offerFcId', 'offerFcName', 'catalogSearch', 'error']);
    }

    /**
     * Al elegir jugador, el importe arranca en su valor de mercado. Es el punto
     * de partida —el técnico lo cambia—, no un precio fijo. Una cesión no
     * lleva importe.
     *
     * Lo normal es que ya lo haya puesto el navegador (ver `offerableValues()`);
     * esto es el respaldo, y sólo rellena un importe vacío: el desplegable
     * viaja junto con el importe al enviar, y aquí se pisaba la cifra que el
     * técnico acababa de escribir.
     */
    public function updatedOfferPlayerId(): void
    {
        if ($this->free() || blank($this->offerPlayerId) || filled($this->offerAmount)) {
            return;
        }

        $value = Player::query()->whereKey($this->offerPlayerId)->value('market_value');

        $this->offerAmount = $value === null ? null : (string) $value;
    }

    public function mayOffer(): bool
    {
        $user = $this->user();

        return $user?->isCoach() === true
            && $user->club_id !== null
            && $this->conversation()?->other($user) !== null;
    }

    public function sendOffer(): void
    {
        $user = $this->user();
        $conversation = $this->conversation();

        if ($user === null || $conversation === null || ! $this->mayOffer()) {
            return;
        }

        if (! $this->withinSendingPace($user)) {
            return;
        }

        $catalogue = $this->toPresident() && $this->asking();

        // Antes salía sin decir nada y la pantalla parecía no haber hecho caso:
        // quien escribía un nombre sin elegirlo de la lista, o dejaba el
        // importe en blanco, no veía ni oferta ni motivo.
        if ($catalogue ? $this->offerFcId === null : blank($this->offerPlayerId)) {
            $this->error = $catalogue
                ? 'Elige al jugador de la lista del catálogo: escribir su nombre no basta.'
                : 'Elige un jugador para la oferta.';

            return;
        }

        if ($this->free() ? blank($this->offerTerm) : blank($this->offerAmount)) {
            $this->error = $this->free() ? 'Elige el plazo de la cesión.' : 'Pon el importe de la oferta.';

            return;
        }

        try {
            Offer::create([
                'conversation_id' => $conversation->getKey(),
                'player_id' => $catalogue ? null : (int) $this->offerPlayerId,
                'fc_id' => $catalogue ? $this->offerFcId : null,
                'external_player' => $catalogue ? $this->offerFcName : null,
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

        $this->reset(['offerPlayerId', 'offerAmount', 'offerTerm', 'offerFcId', 'offerFcName', 'catalogSearch', 'error']);

        // El panel se cierra y la pantalla baja a la oferta SÓLO ahora, cuando
        // ya existe: cerrarlo al pulsar lo hacía desaparecer aunque fallara.
        $this->dispatch('chat-sent', offer: true);
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
        $offer = Offer::query()->with('conversation.participants')->find($offerId);
        $user = $this->user();

        // Sólo sobre una oferta que le toca responder. Sin esto cualquier
        // usuario con sesión cargaba la de un hilo ajeno con sólo cambiar el
        // id, y el importe o el valor de mercado le llegaban en
        // `counterAmount`.
        if ($offer === null || $user === null || ! app(OfferService::class)->mayAnswer($offer, $user)) {
            return;
        }

        $this->counteringOfferId = $offerId;
        // Se contraoferta sobre el valor de mercado cuando lo hay (un jugador
        // del catálogo no lo tiene todavía), y si no sobre lo ofrecido.
        $value = $offer?->player?->market_value ?? ($offer?->amount ?: null);
        $this->counterAmount = $value === null ? null : (string) $value;
        $this->counterTerm = null;
    }

    public function sendCounter(): void
    {
        $offer = $this->counteringOfferId === null ? null : Offer::query()->find($this->counteringOfferId);

        // Una cesión se contraoferta con otro plazo; lo demás, con otro importe.
        if ($offer === null || ($offer->isFree() ? blank($this->counterTerm) : blank($this->counterAmount))) {
            return;
        }

        $amount = $offer->isFree() ? null : (int) $this->counterAmount;
        $term = $offer->isFree() ? $this->counterTerm : null;

        $this->answer(
            $offer->getKey(),
            fn (Offer $offer, User $user) => app(OfferService::class)->counter($offer, $user, $amount, $term),
        );

        $this->reset(['counteringOfferId', 'counterAmount', 'counterTerm']);
    }

    /**
     * La tensión de la negociación, que sólo fija el presidente.
     */
    public function setTension(int $offerId, int $tension): void
    {
        $this->answer($offerId, fn (Offer $offer, User $user) => app(OfferService::class)->setTension(
            $offer,
            $user,
            max(1, min(100, $tension)),
        ));
    }

    /**
     * Lo que el presidente apunta de una negociación: con qué club la lleva y
     * qué tensión tiene. Un solo botón para las dos cosas.
     */
    public function saveNegotiation(int $offerId, ?string $counterpart, int $tension): void
    {
        $this->answer($offerId, function (Offer $offer, User $user) use ($counterpart, $tension): void {
            $service = app(OfferService::class);
            $service->setCounterpart($offer, $user, $counterpart);
            $service->setTension($offer, $user, max(1, min(100, $tension)));
        });
    }

    /**
     * Deja la contraoferta a medias: vuelven los tres botones.
     */
    public function cancelCounter(): void
    {
        $this->reset(['counteringOfferId', 'counterAmount', 'counterTerm']);
    }

    public function maySetTension(Offer $offer): bool
    {
        $user = $this->user();

        return $user?->isAdmin() === true && $offer->isOpen();
    }

    public function mayAnswer(Offer $offer): bool
    {
        $user = $this->user();

        return $user !== null && app(OfferService::class)->mayAnswer($offer, $user);
    }

    /**
     * Si un mensaje MÍO ya lo vio el otro: cuando abrió el hilo por última vez
     * fue en o después de que se enviara. No hace falta una marca por mensaje
     * —`last_read_at` de la pertenencia ya distingue antes/después—.
     */
    public function messageSeen(Message $message): bool
    {
        $conversation = $this->conversation();
        $me = $this->user();

        if ($conversation === null || $me === null) {
            return false;
        }

        $other = $conversation->other($me);
        $lastRead = $other === null ? null : $conversation->participants
            ->firstWhere(fn (User $participant) => $participant->is($other))?->pivot?->last_read_at;

        return $lastRead !== null && $message->created_at !== null && $message->created_at->lessThanOrEqualTo($lastRead);
    }

    public function unreadTotal(): int
    {
        $user = $this->user();

        return array_sum($this->unreadCounts());
    }

    /**
     * Cuántos mensajes y ofertas puede mandar alguien por minuto.
     *
     * Las acciones de Livewire entran por su propio endpoint y no pasan por el
     * `throttle` de las rutas del sitio, así que sin esto un hilo se podía
     * inundar —y con él, el móvil del otro a avisos push— a la velocidad de
     * un bucle. Treinta por minuto no lo nota quien escribe a mano.
     */
    private const SENDS_PER_MINUTE = 30;

    private function withinSendingPace(User $user): bool
    {
        $key = 'chat-send:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, self::SENDS_PER_MINUTE)) {
            $this->error = 'Vas muy rápido: espera '.RateLimiter::availableIn($key).' segundos antes de mandar otro.';

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
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
