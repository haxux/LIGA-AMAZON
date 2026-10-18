@php
    use App\Models\Offer;
    use App\Models\Transfer;
    use Illuminate\Support\Str;

    $me = $this->user();
    $conversation = $this->conversation();
    $other = $conversation?->other($me);

    // Cada estado de una oferta con su color: de un vistazo se lee si sigue viva.
    $statusTones = [
        Offer::STATUS_SENT => ['dot' => 'bg-brand', 'chip' => 'bg-brand/15 text-brand'],
        Offer::STATUS_NEGOTIATING => ['dot' => 'bg-sky-400', 'chip' => 'bg-sky-400/15 text-sky-300'],
        Offer::STATUS_ACCEPTED => ['dot' => 'bg-emerald-400', 'chip' => 'bg-emerald-400/15 text-emerald-300'],
        Offer::STATUS_EXECUTED => ['dot' => 'bg-emerald-400', 'chip' => 'bg-emerald-400/15 text-emerald-300'],
        Offer::STATUS_REJECTED => ['dot' => 'bg-rose-400', 'chip' => 'bg-rose-400/15 text-rose-300'],
    ];

    // Una marca por operación: hacia dónde va el jugador.
    $kindGlyphs = [
        Offer::KIND_BUY => '↓',
        Offer::KIND_SELL => '↑',
        Offer::KIND_LOAN_IN => '⇄',
        Offer::KIND_LOAN_OUT => '↗',
    ];

    $input = 'w-full rounded-xl border border-white/10 bg-surface-alt px-3.5 py-2.5 text-sm text-white placeholder:text-white/30 focus:border-brand/60 focus:outline-none focus:ring-2 focus:ring-brand/20';
@endphp

{{-- Sondeo, no websockets (design D11): este despliegue escala a cero y no
     tiene un proceso permanente donde sostenerlos. `.visible`: con el chat
     fuera de la pantalla no se pregunta nada. --}}
<div class="overflow-hidden rounded-2xl bg-surface shadow-2xl ring-1 ring-white/[0.06] md:grid md:h-[40rem] md:grid-cols-[18rem_1fr] xl:grid-cols-[21rem_1fr]" wire:poll.5s.visible>

    {{-- ─── Los hilos ─────────────────────────────────────────────────── --}}
    {{-- En el móvil se ve una cosa a la vez: la lista, o el hilo abierto. --}}
    <aside class="{{ $conversation ? 'hidden md:flex' : 'flex' }} h-[36rem] min-h-0 flex-col border-white/[0.06] bg-surface-alt md:h-auto md:border-r"
           x-data="{ starting: false, q: '' }">
        <header class="flex items-center justify-between gap-2 px-5 pb-3 pt-5">
            <div>
                <h2 class="font-display text-2xl uppercase leading-none tracking-wide text-white">Chats</h2>
                <p class="mt-1 font-mono text-[10px] tracking-[0.14em] text-white/35">
                    {{ $this->conversations()->count() }} {{ $this->conversations()->count() === 1 ? 'CONVERSACIÓN' : 'CONVERSACIONES' }}
                </p>
            </div>

            <button type="button" x-on:click="starting = ! starting"
                    class="flex size-9 items-center justify-center rounded-full bg-brand text-ink shadow-lg shadow-brand/20 transition hover:scale-105 hover:opacity-95"
                    title="Empezar una conversación" aria-label="Empezar una conversación">
                <svg class="size-5 transition-transform" :class="starting ? 'rotate-45' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
        </header>

        {{-- El buscador filtra la lista en el navegador: son pocos hilos. --}}
        <div class="px-4 pb-3">
            <label class="flex items-center gap-2 rounded-full bg-surface px-3.5 py-2 ring-1 ring-white/[0.06] focus-within:ring-brand/40">
                <svg class="size-4 shrink-0 text-white/35" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" x-model="q" placeholder="Buscar"
                       class="w-full bg-transparent text-sm text-white placeholder:text-white/30 focus:outline-none">
            </label>
        </div>

        {{-- El listín, que se despliega desde el botón de arriba. --}}
        <div x-show="starting" x-cloak class="mx-3 mb-3 max-h-56 overflow-y-auto rounded-xl bg-surface p-1 ring-1 ring-white/[0.06]">
            <p class="px-3 pb-1 pt-2 font-mono text-[9px] tracking-[0.16em] text-brand">NUEVA CONVERSACIÓN</p>

            @foreach ($this->contacts() as $contact)
                <button type="button" wire:click="openWith({{ $contact->id }})" x-on:click="starting = false"
                        wire:key="contacto-{{ $contact->id }}"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-white/[0.06]">
                    <x-chat.avatar :user="$contact" size="size-8" text="text-xs" />
                    <span class="min-w-0 flex-1 truncate font-display text-sm text-white/85">{{ $this->displayName($contact) }}</span>
                </button>
            @endforeach
        </div>

        <div class="min-h-0 flex-1 space-y-0.5 overflow-y-auto px-2 pb-3">
            @forelse ($this->conversations() as $thread)
                @php($with = $thread->other($me))
                @php($unread = $this->unreadCounts()[$thread->id] ?? 0)
                @php($last = $thread->latestMessage)
                @php($isOpen = $conversation?->is($thread))

                <button type="button" wire:click="open({{ $thread->id }})" wire:key="hilo-{{ $thread->id }}"
                        x-show="q === '' || @js(mb_strtolower($with?->name ?? '')).includes(q.toLowerCase())"
                        class="group relative flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition
                               {{ $isOpen ? 'bg-white/[0.07]' : 'hover:bg-white/[0.04]' }}">
                    @if ($isOpen)
                        <span class="absolute inset-y-3 left-0 w-[3px] rounded-full bg-brand" aria-hidden="true"></span>
                    @endif

                    <x-chat.avatar :user="$with" size="size-11" />

                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="truncate font-display text-[17px] font-semibold leading-tight {{ $unread > 0 ? 'text-white' : 'text-white/90' }}">{{ $with?->name }}</span>
                            @if ($last)
                                <span class="shrink-0 font-mono text-[10px] {{ $unread > 0 ? 'text-emerald-400' : 'text-white/35' }}">{{ $last->created_at?->format('H:i') }}</span>
                            @endif
                        </span>

                        <span class="mt-0.5 flex items-center justify-between gap-2">
                            <span class="truncate text-[13px] {{ $unread > 0 ? 'font-medium text-white/75' : 'text-white/45' }}">
                                {{ $last ? Str::limit($last->body, 34) : $this->subtitleFor($with) }}
                            </span>

                            @if ($unread > 0)
                                <span class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 px-1.5 font-display text-[11px] font-bold text-ink shadow shadow-emerald-500/30">
                                    {{ $unread }}
                                </span>
                            @endif
                        </span>
                    </span>
                </button>
            @empty
                <div class="px-5 py-12 text-center">
                    <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-white/[0.05] text-xl" aria-hidden="true">💬</span>
                    <p class="text-sm text-white/55">Todavía no has hablado con nadie.</p>
                    <p class="mt-1 text-sm text-white/35">Pulsa <span class="font-display text-brand">+</span> para empezar.</p>
                </div>
            @endforelse
        </div>
    </aside>

    {{-- ─── El hilo abierto ───────────────────────────────────────────── --}}
    {{-- `min-h-0` en las dos columnas y en la zona de mensajes: sin él, un
         hijo de grid o de flex no puede ser más bajo que su contenido, así que
         con un hilo largo la columna crecía por debajo de la tarjeta y el
         `overflow-hidden` se comía lo de abajo — la barra de escribir y la
         última oferta quedaban recortadas, fuera de la vista. --}}
    <section class="{{ $conversation ? 'flex' : 'hidden md:flex' }} h-[36rem] min-h-0 min-w-0 flex-col md:h-auto">
        @if ($conversation === null)
            <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center"
                 style="background: radial-gradient(ellipse 60% 50% at 50% 40%, rgba(255,184,0,.07), transparent 70%);">
                <span class="flex size-20 items-center justify-center rounded-full bg-white/[0.05] text-4xl ring-1 ring-white/[0.08]" aria-hidden="true">💬</span>
                <div>
                    <p class="font-display text-2xl uppercase tracking-wide text-white/80">Elige una conversación</p>
                    <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-white/40">Aquí se lee el hilo, y desde aquí se negocian jugadores con otro técnico o con el presidente.</p>
                </div>
            </div>
        @else
            <header class="flex items-center gap-3 border-b border-white/[0.06] bg-surface-alt/80 px-4 py-3 backdrop-blur md:px-5">
                {{-- En móvil, donde la lista y el hilo no caben a la vez, la
                     salida del hilo es la flecha. --}}
                <button type="button" wire:click="close"
                        class="-ml-1 flex size-9 shrink-0 items-center justify-center rounded-full text-white/70 hover:bg-white/[0.08] md:hidden"
                        aria-label="Volver a la lista">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                </button>

                <x-chat.avatar :user="$other" size="size-11" />

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-xl font-semibold leading-tight text-white">{{ $other?->name }}</span>
                    <span class="mt-0.5 inline-flex max-w-full items-center truncate rounded-full bg-white/[0.07] px-2.5 py-0.5 font-mono text-[9px] tracking-[0.14em] text-white/55">
                        {{ mb_strtoupper($this->subtitleFor($other) ?? '') }}
                    </span>
                </span>

            </header>

            {{-- Mensajes: el fondo lleva un resplandor suave y no una rejilla,
                 que cansaba. Se baja solo al último al abrir el hilo. --}}
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 md:px-6"
                 wire:key="hilo-abierto-{{ $conversation->id }}"
                 x-data="{
                     stick: true,
                     toBottom() { this.$el.scrollTop = this.$el.scrollHeight },
                 }"
                 x-init="$nextTick(() => toBottom()); new MutationObserver(() => { if (stick) toBottom() }).observe($el, { childList: true, subtree: true })"
                 x-on:scroll.passive="stick = $el.scrollHeight - $el.scrollTop - $el.clientHeight < 80"
                 x-on:chat-sent.window="stick = true; $nextTick(() => toBottom())"
                 style="background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(255,184,0,.045), transparent 70%), #232323;">
                @php($lastDay = null)
                @php($prev = null)

                @php($timeline = $this->timeline())
                @php($entries = $timeline['entries'])
                @php($earlier = $timeline['earlier'])

                {{-- Sólo lo último del hilo viaja en cada repintado; lo de
                     antes, a petición. --}}
                @if ($earlier)
                    <div class="mb-4 flex justify-center">
                        <button type="button" wire:click="showEarlier"
                                class="rounded-full bg-white/[0.06] px-4 py-1.5 font-mono text-[10px] tracking-[0.14em] text-white/55 transition hover:bg-white/[0.1] hover:text-white/80">
                            VER ANTERIORES
                        </button>
                    </div>
                @endif

                @foreach ($entries as $entry)
                    @php($day = $entry->created_at?->format('d/m/Y'))

                    @if ($day !== $lastDay)
                        @php($lastDay = $day)
                        @php($prev = null)
                        <div class="my-4 flex items-center gap-3">
                            <span class="h-px flex-1 bg-white/[0.07]"></span>
                            <span class="rounded-full bg-white/[0.06] px-3 py-1 font-mono text-[10px] uppercase tracking-[0.14em] text-white/50">
                                {{ $entry->created_at?->isToday() ? 'Hoy' : ($entry->created_at?->isYesterday() ? 'Ayer' : $day) }}
                            </span>
                            <span class="h-px flex-1 bg-white/[0.07]"></span>
                        </div>
                    @endif

                    @if ($entry instanceof Offer)
                        @php($prev = null)
                        @php($mineOffer = (int) $entry->moved_by === (int) $me?->id)
                        @php($tone = $statusTones[$entry->status] ?? $statusTones[Offer::STATUS_SENT])

                        {{-- La oferta se lee entre los mensajes porque ahí es
                             donde se hizo, y del lado de quien la movió. --}}
                        <div class="my-3 flex {{ $mineOffer ? 'justify-end' : 'justify-start' }}" wire:key="oferta-{{ $entry->id }}">
                            <div class="w-full max-w-[22rem] overflow-hidden rounded-2xl bg-surface-alt shadow-lg shadow-black/20 ring-1 ring-white/[0.08]">
                                <div class="flex items-center justify-between gap-2 border-b border-white/[0.06] bg-white/[0.03] px-4 py-2.5">
                                    <span class="flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.14em] text-brand">
                                        <span class="flex size-6 items-center justify-center rounded-full bg-brand/15 font-display text-sm font-bold leading-none">{{ $kindGlyphs[$entry->kind] ?? '·' }}</span>
                                        {{ Offer::KINDS_SHORT[$entry->kind] ?? 'Oferta' }}
                                    </span>

                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-mono text-[10px] tracking-[0.08em] {{ $tone['chip'] }}">
                                        <span class="size-1.5 rounded-full {{ $tone['dot'] }}"></span>
                                        {{ Offer::STATUSES[$entry->status] }}
                                    </span>
                                </div>

                                <div class="px-4 pb-3 pt-3.5">
                                    <div class="font-display text-xl font-semibold leading-tight text-white">{{ $entry->playerName() }}</div>

                                    @if ($entry->isFree())
                                        <div class="mt-1 flex items-baseline gap-2">
                                            <span class="text-[13px] text-white/45">en cesión,</span>
                                            <span class="font-display text-2xl font-bold text-brand">{{ Transfer::LOAN_TERMS[$entry->loan_term] ?? 'sin plazo' }}</span>
                                        </div>
                                    @else
                                        <div class="mt-1 flex items-baseline gap-2">
                                            <span class="text-[13px] text-white/45">por</span>
                                            <span class="font-display text-3xl font-bold leading-none text-brand">{{ number_format($entry->amount, 0, ',', '.') }}</span>
                                        </div>
                                    @endif

                                    <div class="mt-2 font-mono text-[10px] tracking-[0.08em] text-white/35">
                                        ENVIADA POR {{ mb_strtoupper($entry->mover?->name ?? '') }} · {{ $entry->created_at?->format('H:i') }}
                                    </div>

                                    {{-- Con el presidente: con qué club negocia (lo escribe
                                         él) y la tensión que lleva. Los dos los ve el técnico;
                                         sólo el presidente los toca. --}}
                                    @if ($entry->withPresident() && ($entry->tension !== null || $entry->counterpart || $entry->isOpen()))
                                        @php($tension = $entry->tension)
                                        @php($tensionTone = $tension === null ? 'bg-white/20' : ($tension >= 70 ? 'bg-rose-500' : ($tension >= 40 ? 'bg-amber-400' : 'bg-emerald-500')))

                                        <div class="mt-3 space-y-2.5 rounded-xl bg-black/20 px-3 py-3"
                                             x-data="{ value: {{ $tension ?? 50 }}, club: @js($entry->counterpart ?? '') }">
                                            <div class="flex items-center justify-between gap-2 font-mono text-[9px] uppercase tracking-[0.14em] text-white/50">
                                                <span class="shrink-0">Negocia con</span>
                                                <span class="truncate text-right {{ $entry->counterpart ? 'font-display text-sm font-semibold normal-case tracking-normal text-white' : 'text-white/35' }}">
                                                    {{ $entry->counterpart ?? 'Club sin indicar' }}
                                                </span>
                                            </div>

                                            <div>
                                                <div class="flex items-center justify-between font-mono text-[9px] uppercase tracking-[0.14em] text-white/50">
                                                    <span>Tensión de la negociación</span>
                                                    <span class="text-white/80">{{ $tension === null ? 'Sin valorar' : $tension.'%' }}</span>
                                                </div>
                                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                                                    <div class="h-full rounded-full {{ $tensionTone }}" style="width: {{ $tension ?? 0 }}%"></div>
                                                </div>
                                            </div>

                                            @if ($this->maySetTension($entry))
                                                <div class="space-y-2 border-t border-white/[0.06] pt-2.5">
                                                    <label class="block">
                                                        <span class="mb-1 block font-mono text-[9px] tracking-[0.14em] text-brand">CLUB CON EL QUE NEGOCIAS</span>
                                                        <input type="text" x-model="club" maxlength="120" placeholder="Ej.: Paris Saint-Germain"
                                                               class="w-full rounded-lg border border-white/10 bg-surface px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none">
                                                    </label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="range" min="1" max="100" x-model="value" class="h-1 flex-1 accent-amber-400" aria-label="Tensión">
                                                        <span class="w-9 text-right font-mono text-[11px] text-white/70" x-text="value + '%'"></span>
                                                    </div>
                                                    <button type="button" x-on:click="$wire.saveNegotiation({{ $entry->id }}, club, parseInt(value))"
                                                            class="w-full rounded-lg border border-brand/40 bg-brand/10 px-3 py-2 font-display text-xs font-bold uppercase tracking-[0.08em] text-brand transition hover:bg-brand/20">
                                                        Guardar
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if ($this->mayAnswer($entry))
                                    @if ($counteringOfferId === $entry->id)
                                        {{-- Contraofertando: los tres botones se apartan y queda
                                             sólo lo que hace falta, para no pulsar «Aceptar»
                                             creyendo que se manda la cifra nueva. --}}
                                        <div class="border-t border-white/[0.06] bg-white/[0.02] p-3">
                                            <p class="mb-2 font-mono text-[9px] tracking-[0.14em] text-brand">
                                                {{ $entry->isFree() ? 'TU PLAZO' : 'TU CONTRAOFERTA' }}
                                            </p>

                                            <form wire:submit.prevent="sendCounter" class="space-y-2">
                                                @if ($entry->isFree())
                                                    <select wire:model="counterTerm" class="{{ $input }}">
                                                        <option value="">Plazo…</option>
                                                        @foreach ($this->loanTerms() as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="number" min="1" wire:model="counterAmount" placeholder="Importe"
                                                           class="{{ $input }} font-display text-lg font-bold">
                                                @endif

                                                <div class="grid grid-cols-2 gap-2">
                                                    <button type="button" wire:click="cancelCounter"
                                                            class="rounded-lg border border-white/15 px-3 py-2.5 font-display text-xs font-bold uppercase tracking-[0.08em] text-white/70 transition hover:bg-white/[0.08]">
                                                        Cancelar
                                                    </button>
                                                    <button type="submit" wire:loading.attr="disabled" wire:target="sendCounter"
                                                            class="rounded-lg bg-brand px-3 py-2.5 font-display text-xs font-bold uppercase tracking-[0.08em] text-ink shadow shadow-brand/20 transition hover:opacity-90 disabled:opacity-50">
                                                        Enviar contraoferta
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    @else
                                        @php($terms = $entry->isFree() ? (Transfer::LOAN_TERMS[$entry->loan_term] ?? 'sin plazo') : number_format($entry->amount, 0, ',', '.'))

                                        {{-- Aceptar va solo y arriba: es la decisión que cierra
                                             el trato, y se confirma antes. Debajo, las otras dos. --}}
                                        <div class="space-y-2 border-t border-white/[0.06] bg-white/[0.02] p-3">
                                            <button type="button" wire:click="acceptOffer({{ $entry->id }})"
                                                    wire:confirm="¿Aceptar {{ $entry->playerName() }} por {{ $terms }}? Quedará acordado y pasará a Transferencias."
                                                    wire:loading.attr="disabled" wire:target="acceptOffer({{ $entry->id }})"
                                                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 px-3 py-2.5 font-display text-sm font-bold uppercase tracking-[0.08em] text-ink shadow shadow-emerald-500/20 transition hover:bg-emerald-400 disabled:opacity-50">
                                                <span aria-hidden="true">✓</span> Aceptar
                                            </button>

                                            <div class="grid grid-cols-2 gap-2">
                                                <button type="button" wire:click="startCounter({{ $entry->id }})"
                                                        class="flex items-center justify-center gap-1.5 rounded-lg border border-brand/40 px-2 py-2 font-display text-xs font-bold uppercase tracking-[0.08em] text-brand transition hover:bg-brand/10">
                                                    <span aria-hidden="true">↺</span> Contraofertar
                                                </button>
                                                <button type="button" wire:click="rejectOffer({{ $entry->id }})"
                                                        wire:confirm="¿Rechazar esta oferta? La negociación se cierra."
                                                        wire:loading.attr="disabled" wire:target="rejectOffer({{ $entry->id }})"
                                                        class="flex items-center justify-center gap-1.5 rounded-lg border border-rose-400/30 px-2 py-2 font-display text-xs font-bold uppercase tracking-[0.08em] text-rose-300 transition hover:bg-rose-500/15 disabled:opacity-50">
                                                    <span aria-hidden="true">✕</span> Rechazar
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                @elseif ($entry->isOpen())
                                    {{-- Quien la mandó no se contesta a sí mismo: que se
                                         vea que está esperando al otro. --}}
                                    <div class="border-t border-white/[0.06] px-4 py-2.5 font-mono text-[10px] tracking-[0.1em] text-white/40">
                                        ESPERANDO RESPUESTA…
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        @php($mine = (int) $entry->user_id === (int) $me?->id)
                        @php($first = $prev === null || $prev !== $entry->user_id)
                        @php($prev = $entry->user_id)
                        @php($next = $entries->get($loop->index + 1))
                        @php($endsGroup = ! ($next instanceof \App\Models\Message) || (int) $next->user_id !== (int) $entry->user_id || $next->created_at?->format('d/m/Y') !== $day)

                        <div class="flex items-end gap-2 {{ $mine ? 'justify-end' : 'justify-start' }} {{ $first ? 'mt-3' : 'mt-0.5' }}" wire:key="mensaje-{{ $entry->id }}">
                            @if (! $mine)
                                {{-- El retrato sólo al final de cada racha, como
                                     en cualquier mensajería: no se repite. --}}
                                <span class="w-7 shrink-0">
                                    @if ($endsGroup)
                                        <x-chat.avatar :user="$other" size="size-7" text="text-[10px]" />
                                    @endif
                                </span>
                            @endif

                            <div class="max-w-[78%] px-3.5 py-2 shadow-sm md:max-w-[70%]
                                        {{ $mine ? 'bg-gradient-to-br from-brand to-amber-500 text-ink' : 'bg-surface-muted text-white ring-1 ring-white/[0.05]' }}
                                        {{ $mine ? ($endsGroup ? 'rounded-2xl rounded-br-md' : 'rounded-2xl') : ($endsGroup ? 'rounded-2xl rounded-bl-md' : 'rounded-2xl') }}">
                                <div class="whitespace-pre-line break-words text-[14.5px] leading-snug">{{ $entry->body }}</div>
                                <div class="mt-0.5 flex items-center justify-end gap-1 font-mono text-[9px] {{ $mine ? 'text-ink/55' : 'text-white/35' }}">
                                    <span>{{ $entry->created_at?->format('H:i') }}</span>
                                    @if ($mine)
                                        @if ($this->messageSeen($entry))
                                            <span class="font-bold text-ink/80" title="Visto">✓✓ Visto</span>
                                        @else
                                            <span title="Enviado">✓ Enviado</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- ─── Barra de escritura ────────────────────────────────── --}}
            <div class="border-t border-white/[0.06] bg-surface-alt px-3 py-3 md:px-4"
                 x-data="{ offering: false }"
                 x-on:chat-sent.window="if ($event.detail?.offer) offering = false">
                @if ($error)
                    <p class="mb-2 rounded-lg bg-rose-500/10 px-3 py-2 font-mono text-[10px] uppercase tracking-[0.1em] text-rose-300">{{ $error }}</p>
                @endif

                @if ($this->mayOffer())
                    <div x-show="offering" x-cloak x-transition.opacity class="mb-3 max-h-[50vh] overflow-y-auto rounded-2xl bg-surface p-4 ring-1 ring-brand/25">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-brand">Proponer una operación</span>
                            <button type="button" x-on:click="offering = false" class="text-white/35 hover:text-white" aria-label="Cerrar">✕</button>
                        </div>

                        <div class="grid gap-2.5 sm:grid-cols-2">
                            {{-- La operación manda: decide de qué plantilla se
                                 elige jugador y si lo que se pacta es dinero o
                                 plazo. --}}
                            <label class="block sm:col-span-2">
                                <span class="mb-1 block font-mono text-[9px] tracking-[0.14em] text-white/40">OPERACIÓN</span>
                                <select wire:model.live="offerKind" class="{{ $input }}">
                                    @foreach ($this->offerKinds() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="sm:col-span-2">
                                <span class="mb-1 block font-mono text-[9px] tracking-[0.14em] text-white/40">JUGADOR</span>

                                @if ($this->toPresident() && $this->asking())
                                    {{-- Al presidente se le pide del catálogo de EA
                                         FC: se escribe el nombre y se elige. --}}
                                    <div class="relative">
                                        @if ($offerFcId)
                                            <div class="flex items-center justify-between gap-2 rounded-xl border border-brand/40 bg-brand/10 px-3.5 py-2.5 text-sm text-white">
                                                <span class="truncate font-medium">{{ $offerFcName }}</span>
                                                <button type="button" wire:click="$set('offerFcId', null)" class="text-white/50 hover:text-white" title="Quitar">✕</button>
                                            </div>
                                        @else
                                            <input type="text" wire:model.live.debounce.300ms="catalogSearch" placeholder="Escribe un nombre: Mbappe, Ekitike…"
                                                   class="{{ $input }}">

                                            @if ($this->catalogResults()->isNotEmpty())
                                                <div class="absolute bottom-full left-0 z-10 mb-1.5 max-h-56 w-full overflow-y-auto rounded-xl bg-surface-alt p-1 shadow-2xl ring-1 ring-white/10">
                                                    @foreach ($this->catalogResults() as $ficha)
                                                        <button type="button" wire:click="selectCatalog({{ $ficha->fc_id }})" wire:key="fc-{{ $ficha->fc_id }}"
                                                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm text-white transition hover:bg-white/[0.07]">
                                                            <span class="truncate">{{ $ficha->nombre() }}</span>
                                                            <span class="shrink-0 font-mono text-[10px] text-white/45">
                                                                <span class="font-bold text-brand">{{ $ficha->overall_rating }}</span> {{ $ficha->position }} · {{ $ficha->club ?? 'sin club' }}
                                                            </span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <select wire:model="offerPlayerId" class="{{ $input }}">
                                        <option value="">{{ $this->asking() ? 'Su jugador…' : 'Tu jugador…' }}</option>
                                        @foreach ($this->offerableOptions() as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>

                            <label class="block">
                                <span class="mb-1 block font-mono text-[9px] tracking-[0.14em] text-white/40">{{ $this->free() ? 'PLAZO' : 'IMPORTE' }}</span>
                                @if ($this->free())
                                    <select wire:model="offerTerm" class="{{ $input }}">
                                        <option value="">Plazo…</option>
                                        @foreach ($this->loanTerms() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="number" min="1" wire:model="offerAmount" placeholder="Importe" class="{{ $input }}">
                                @endif
                            </label>

                            <div class="flex items-end">
                                <button type="button" wire:click="sendOffer" wire:loading.attr="disabled" wire:target="sendOffer"
                                        class="w-full rounded-xl bg-brand px-4 py-2.5 font-display text-sm font-bold uppercase tracking-[0.1em] text-ink shadow-lg shadow-brand/20 transition hover:opacity-90">
                                    Enviar oferta
                                </button>
                            </div>
                        </div>

                        <p class="mt-3 text-[11px] leading-relaxed text-white/40">
                            {{ $this->free()
                                ? 'Una cesión no tiene coste: lo que se pacta es el plazo.'
                                : 'Aceptarla no mueve nada por sí sola: queda acordada y el administrador la cierra.' }}
                            @if ($this->toPresident() && $this->asking())
                                Sólo salen jugadores que no juegan ya en un club de la liga.
                            @endif
                            Los jugadores cedidos no se negocian hasta que acabe la cesión.
                        </p>
                    </div>
                @endif

                <form wire:submit.prevent="send" class="flex items-end gap-2">
                    @if ($this->mayOffer())
                        <button type="button" x-on:click="offering = ! offering"
                                class="flex h-11 shrink-0 items-center gap-1.5 rounded-full border border-white/10 px-3.5 font-display text-sm font-semibold uppercase tracking-[0.06em] text-white/75 transition hover:border-brand/50 hover:text-brand"
                                :class="offering ? 'border-brand/50 text-brand' : ''"
                                title="Ofertar por un jugador">
                            <span aria-hidden="true">💰</span>
                            <span class="hidden sm:inline">Oferta</span>
                        </button>
                    @endif

                    {{-- Enter envía; Mayúsculas+Enter hace un salto de línea. --}}
                    <textarea wire:model="body" rows="1" maxlength="{{ \App\Models\Message::MAX_LENGTH }}" placeholder="Escribe un mensaje"
                              x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $el.form.requestSubmit(); }"
                              class="max-h-28 min-h-11 w-full resize-none rounded-3xl border border-white/10 bg-surface px-4 py-2.5 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/15"></textarea>

                    <button type="submit" wire:loading.attr="disabled" wire:target="send"
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand to-amber-500 text-ink shadow-lg shadow-brand/25 transition hover:scale-105 disabled:opacity-50"
                            title="Enviar" aria-label="Enviar">
                        <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4 21 12 3.4 3.6l-.1 6.5L15 12 3.3 13.9l.1 6.5Z"/></svg>
                    </button>
                </form>
            </div>
        @endif
    </section>
</div>
