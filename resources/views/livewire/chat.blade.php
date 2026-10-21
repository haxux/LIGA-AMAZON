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

    $input = 'w-full rounded-[6px] border border-white/10 bg-surface-alt px-3.5 py-2.5 text-sm text-white placeholder:text-white/30 focus:border-brand/60 focus:outline-none focus:ring-2 focus:ring-brand/20';

    // Los iconos van dibujados, en línea, como los del resto del sitio: los
    // emojis cambian de aspecto en cada teléfono.
    $bubbleIcon = '<path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-7l-5 4v-4H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z" />';
    $offerIcon = '<path d="M7 7h10l-3-3M17 17H7l3 3" /><path d="M17 7v4M7 17v-4" />';
@endphp

{{-- Sondeo, no websockets (design D11): el alojamiento compartido no tiene un
     proceso permanente donde sostenerlos. `.visible`: con el chat
     fuera de la pantalla no se pregunta nada. --}}
<div class="overflow-hidden rounded-md bg-surface ring-1 ring-white/[0.06] md:grid md:h-[40rem] md:grid-cols-[18rem_1fr] xl:grid-cols-[21rem_1fr]" wire:poll.5s.visible>

    {{-- ─── Los hilos ─────────────────────────────────────────────────── --}}
    {{-- En el móvil se ve una cosa a la vez: la lista, o el hilo abierto. --}}
    <aside class="{{ $conversation ? 'hidden md:flex' : 'flex' }} h-[36rem] min-h-0 flex-col border-white/[0.06] bg-surface-alt md:h-auto md:border-r"
           x-data="{ starting: false, q: '' }">
        <header class="flex items-center justify-between gap-2 px-5 pb-3 pt-5">
            <div>
                <h2 class="flex items-center gap-2.5 font-display text-2xl font-extrabold uppercase italic leading-none tracking-wide text-white">
                    <span class="h-5 w-1.5 shrink-0 -skew-x-12 rounded-[1px] bg-brand" aria-hidden="true"></span>
                    Chats
                </h2>
                <p class="mt-1 font-mono text-[10px] tracking-[0.14em] text-white/60">
                    {{ $this->conversations()->count() }} {{ $this->conversations()->count() === 1 ? 'CONVERSACIÓN' : 'CONVERSACIONES' }}
                </p>
            </div>

            <button type="button" x-on:click="starting = ! starting"
                    class="flex size-9 -skew-x-12 items-center justify-center rounded-[4px] bg-brand text-black/85 transition hover:opacity-90"
                    title="Empezar una conversación" aria-label="Empezar una conversación">
                <svg class="size-5 skew-x-12 transition-transform" :class="starting ? 'rotate-45' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
        </header>

        {{-- El buscador filtra la lista en el navegador: son pocos hilos. --}}
        <div class="px-4 pb-3">
            <label class="flex items-center gap-2 rounded-[6px] bg-surface px-3.5 py-2 ring-1 ring-white/[0.06] focus-within:ring-brand/40">
                <svg class="size-4 shrink-0 text-white/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" x-model="q" placeholder="Buscar"
                       class="w-full bg-transparent text-sm text-white placeholder:text-white/30 focus:outline-none">
            </label>
        </div>

        {{-- El listín, que se despliega desde el botón de arriba. --}}
        <div x-show="starting" x-cloak class="mx-3 mb-3 max-h-56 overflow-y-auto rounded-md bg-surface p-1 ring-1 ring-white/[0.06]">
            <p class="px-3 pb-1 pt-2 font-mono text-[9px] tracking-[0.16em] text-brand">NUEVA CONVERSACIÓN</p>

            @foreach ($this->contacts() as $contact)
                <button type="button" wire:click="openWith({{ $contact->id }})" x-on:click="starting = false"
                        wire:key="contacto-{{ $contact->id }}"
                        class="flex w-full items-center gap-3 rounded-[4px] px-3 py-2 text-left transition hover:bg-white/[0.06]">
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
                        class="group relative flex w-full items-center gap-3 rounded-[5px] px-3 py-3 text-left transition
                               {{ $isOpen ? 'bg-white/[0.07]' : 'hover:bg-white/[0.04]' }}">
                    @if ($isOpen)
                        <span class="absolute inset-y-3 left-0 w-1.5 -skew-x-12 rounded-[1px] bg-brand" aria-hidden="true"></span>
                    @endif

                    <x-chat.avatar :user="$with" size="size-11" />

                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="truncate font-display text-[17px] font-bold italic leading-tight {{ $unread > 0 ? 'text-white' : 'text-white/90' }}">{{ $with?->name }}</span>
                            @if ($last)
                                <span class="shrink-0 font-mono text-[10px] {{ $unread > 0 ? 'text-emerald-400' : 'text-white/60' }}">{{ $last->created_at?->format('H:i') }}</span>
                            @endif
                        </span>

                        <span class="mt-0.5 flex items-center justify-between gap-2">
                            <span class="truncate text-[13px] {{ $unread > 0 ? 'font-medium text-white/75' : 'text-white/60' }}">
                                {{ $last ? Str::limit($last->body, 34) : $this->subtitleFor($with) }}
                            </span>

                            @if ($unread > 0)
                                <span class="grid h-5 min-w-5 shrink-0 -skew-x-12 place-items-center rounded-[3px] bg-emerald-500 px-1.5 font-display text-[11px] font-bold text-black/85">
                                    <span class="skew-x-12">{{ $unread }}</span>
                                </span>
                            @endif
                        </span>
                    </span>
                </button>
            @empty
                <div class="px-5 py-12 text-center">
                    <svg viewBox="0 0 24 24" class="mx-auto mb-3 size-10 text-white/20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">{!! $bubbleIcon !!}</svg>
                    <p class="text-sm text-white/55">Todavía no has hablado con nadie.</p>
                    <p class="mt-1 text-sm text-white/60">Pulsa <span class="font-display text-brand">+</span> para empezar.</p>
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
            <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
                <svg viewBox="0 0 24 24" class="size-16 text-white/15" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true">{!! $bubbleIcon !!}</svg>
                <div>
                    <p class="font-display -skew-x-6 text-2xl font-extrabold uppercase italic tracking-wide text-white/80">Elige una conversación</p>
                    <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-white/60">Aquí se lee el hilo, y desde aquí se negocian jugadores con otro técnico o con el presidente.</p>
                </div>
            </div>
        @else
            <header class="flex items-center gap-3 border-b border-white/[0.06] bg-surface-alt px-4 py-3 md:px-5">
                {{-- En móvil, donde la lista y el hilo no caben a la vez, la
                     salida del hilo es la flecha. --}}
                <button type="button" wire:click="close"
                        class="-ml-1 flex size-9 shrink-0 items-center justify-center rounded-full text-white/70 hover:bg-white/[0.08] md:hidden"
                        aria-label="Volver a la lista">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                </button>

                <x-chat.avatar :user="$other" size="size-11" />

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-xl font-extrabold uppercase italic leading-tight text-white">{{ $other?->name }}</span>
                    <span class="mt-1 inline-grid max-w-full -skew-x-12 rounded-[2px] bg-white/10 px-2 py-0.5">
                        <span class="skew-x-12 truncate font-mono text-[9px] tracking-[0.14em] text-white/70">{{ mb_strtoupper($this->subtitleFor($other) ?? '') }}</span>
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
                 style="background: #232323;">
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
                                class="rounded-[4px] bg-white/[0.06] px-4 py-1.5 font-mono text-[10px] tracking-[0.14em] text-white/55 transition hover:bg-white/[0.1] hover:text-white/80">
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
                            <span class="rounded-[3px] bg-white/[0.06] px-3 py-1 font-mono text-[10px] uppercase tracking-[0.14em] text-white/60">
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
                            <div class="w-full max-w-[22rem] overflow-hidden rounded-md border-t-4 border-brand bg-surface-alt ring-1 ring-white/[0.08]">
                                <div class="flex items-center justify-between gap-2 border-b border-white/[0.06] bg-white/[0.03] px-4 py-2.5">
                                    <span class="inline-grid -skew-x-12 rounded-[3px] bg-brand px-2 py-0.5">
                                        <span class="flex skew-x-12 items-center gap-1.5 font-display text-sm font-bold uppercase italic tracking-wide text-black/85">
                                            <span aria-hidden="true">{{ $kindGlyphs[$entry->kind] ?? '·' }}</span>
                                            {{ Offer::KINDS_SHORT[$entry->kind] ?? 'Oferta' }}
                                        </span>
                                    </span>

                                    <span class="inline-flex items-center gap-1.5 rounded-[3px] px-2.5 py-0.5 font-mono text-[10px] tracking-[0.08em] {{ $tone['chip'] }}">
                                        <span class="size-1.5 -skew-x-12 rounded-[1px] {{ $tone['dot'] }}"></span>
                                        {{ Offer::STATUSES[$entry->status] }}
                                    </span>
                                </div>

                                <div class="px-4 pb-3 pt-3.5">
                                    <div class="font-display text-2xl font-extrabold uppercase italic leading-tight text-white">{{ $entry->playerName() }}</div>

                                    @if ($entry->isFree())
                                        <div class="mt-1 flex items-baseline gap-2">
                                            <span class="text-[13px] text-white/60">en cesión,</span>
                                            <span class="-skew-x-8 font-display text-2xl font-extrabold text-brand">{{ Transfer::LOAN_TERMS[$entry->loan_term] ?? 'sin plazo' }}</span>
                                        </div>
                                    @else
                                        <div class="mt-1 flex items-baseline gap-2">
                                            <span class="text-[13px] text-white/60">por</span>
                                            <span class="-skew-x-8 font-display text-4xl font-extrabold leading-none tabular-nums text-brand">{{ number_format($entry->amount, 0, ',', '.') }}</span>
                                        </div>
                                    @endif

                                    <div class="mt-2 font-mono text-[10px] tracking-[0.08em] text-white/60">
                                        ENVIADA POR {{ mb_strtoupper($entry->mover?->name ?? '') }} · {{ $entry->created_at?->format('H:i') }}
                                    </div>

                                    {{-- Con el presidente: con qué club negocia (lo escribe
                                         él) y la tensión que lleva. Los dos los ve el técnico;
                                         sólo el presidente los toca. --}}
                                    @if ($entry->withPresident() && ($entry->tension !== null || $entry->counterpart || $entry->isOpen()))
                                        @php($tension = $entry->tension)
                                        @php($tensionTone = $tension === null ? 'bg-white/20' : ($tension >= 70 ? 'bg-rose-500' : ($tension >= 40 ? 'bg-amber-400' : 'bg-emerald-500')))
                                        @php($tensionBlocks = $tension === null ? 0 : (int) max(1, round($tension / 10)))

                                        <div class="mt-3 space-y-2.5 rounded-[5px] bg-black/20 px-3 py-3"
                                             x-data="{ value: {{ $tension ?? 50 }}, club: @js($entry->counterpart ?? '') }">
                                            <div class="flex items-center justify-between gap-2 font-mono text-[9px] uppercase tracking-[0.14em] text-white/60">
                                                <span class="shrink-0">Negocia con</span>
                                                <span class="truncate text-right {{ $entry->counterpart ? 'font-display text-sm font-semibold normal-case tracking-normal text-white' : 'text-white/60' }}">
                                                    {{ $entry->counterpart ?? 'Club sin indicar' }}
                                                </span>
                                            </div>

                                            <div>
                                                <div class="flex items-center justify-between font-mono text-[9px] uppercase tracking-[0.14em] text-white/60">
                                                    <span>Tensión de la negociación</span>
                                                    <span class="text-white/80">{{ $tension === null ? 'Sin valorar' : $tension.'%' }}</span>
                                                </div>
                                                {{-- El medidor de diez bloques de todo el sitio, en el
                                                     color de la tensión. --}}
                                                <div class="mt-1.5 flex gap-0.5" aria-hidden="true">
                                                    @for ($i = 1; $i <= 10; $i++)
                                                        <span class="h-2.5 flex-1 -skew-x-12 rounded-[1px] {{ $i <= $tensionBlocks ? $tensionTone : 'bg-white/10' }}"></span>
                                                    @endfor
                                                </div>
                                            </div>

                                            @if ($this->maySetTension($entry))
                                                <div class="space-y-2 border-t border-white/[0.06] pt-2.5">
                                                    <label class="block">
                                                        <span class="mb-1 block font-mono text-[9px] tracking-[0.14em] text-brand">CLUB CON EL QUE NEGOCIAS</span>
                                                        <input type="text" x-model="club" maxlength="120" placeholder="Ej.: Paris Saint-Germain"
                                                               class="w-full rounded-[5px] border border-white/10 bg-surface px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none">
                                                    </label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="range" min="1" max="100" x-model="value" class="h-1 flex-1 accent-amber-400" aria-label="Tensión">
                                                        <span class="w-9 text-right font-mono text-[11px] text-white/70" x-text="value + '%'"></span>
                                                    </div>
                                                    <button type="button" x-on:click="$wire.saveNegotiation({{ $entry->id }}, club, parseInt(value))"
                                                            class="w-full rounded-[4px] border border-brand/40 bg-brand/10 px-3 py-2 font-display text-xs font-bold uppercase italic tracking-[0.08em] text-brand transition hover:bg-brand/20">
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
                                                            class="rounded-[4px] border border-white/15 px-3 py-2.5 font-display text-xs font-bold uppercase italic tracking-[0.08em] text-white/70 transition hover:bg-white/[0.08]">
                                                        Cancelar
                                                    </button>
                                                    <button type="submit" wire:loading.attr="disabled" wire:target="sendCounter"
                                                            class="rounded-[4px] bg-brand px-3 py-2.5 font-display text-xs font-bold uppercase italic tracking-[0.08em] text-black/85 transition hover:opacity-90 disabled:opacity-50">
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
                                                    class="flex w-full items-center justify-center gap-2 rounded-[4px] bg-emerald-500 px-3 py-2.5 font-display text-sm font-bold uppercase italic tracking-[0.08em] text-black/85 transition hover:bg-emerald-400 disabled:opacity-50">
                                                <span aria-hidden="true">✓</span> Aceptar
                                            </button>

                                            <div class="grid grid-cols-2 gap-2">
                                                <button type="button" wire:click="startCounter({{ $entry->id }})"
                                                        class="flex items-center justify-center gap-1.5 rounded-[4px] border border-brand/40 px-2 py-2 font-display text-xs font-bold uppercase italic tracking-[0.08em] text-brand transition hover:bg-brand/10">
                                                    <span aria-hidden="true">↺</span> Contraofertar
                                                </button>
                                                <button type="button" wire:click="rejectOffer({{ $entry->id }})"
                                                        wire:confirm="¿Rechazar esta oferta? La negociación se cierra."
                                                        wire:loading.attr="disabled" wire:target="rejectOffer({{ $entry->id }})"
                                                        class="flex items-center justify-center gap-1.5 rounded-[4px] border border-rose-400/30 px-2 py-2 font-display text-xs font-bold uppercase italic tracking-[0.08em] text-rose-300 transition hover:bg-rose-500/15 disabled:opacity-50">
                                                    <span aria-hidden="true">✕</span> Rechazar
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                @elseif ($entry->isOpen())
                                    {{-- Quien la mandó no se contesta a sí mismo: que se
                                         vea que está esperando al otro. --}}
                                    <div class="border-t border-white/[0.06] px-4 py-2.5 font-mono text-[10px] tracking-[0.1em] text-white/60">
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

                            <div class="max-w-[78%] px-3.5 py-2 md:max-w-[70%]
                                        {{ $mine ? 'bg-brand text-black/85' : 'bg-surface-muted text-white' }}
                                        {{ $mine ? ($endsGroup ? 'rounded-lg rounded-br-[2px]' : 'rounded-lg') : ($endsGroup ? 'rounded-lg rounded-bl-[2px]' : 'rounded-lg') }}">
                                <div class="whitespace-pre-line break-words text-[14.5px] leading-snug">{{ $entry->body }}</div>
                                <div class="mt-0.5 flex items-center justify-end gap-1 font-mono text-[9px] {{ $mine ? 'text-black/55' : 'text-white/60' }}">
                                    <span>{{ $entry->created_at?->format('H:i') }}</span>
                                    @if ($mine)
                                        @if ($this->messageSeen($entry))
                                            <span class="font-bold text-black/80" title="Visto">✓✓ Visto</span>
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
                    <div x-show="offering" x-cloak x-transition.opacity class="mb-3 max-h-[50vh] overflow-y-auto rounded-md border-t-4 border-brand bg-surface p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="font-display text-base font-extrabold uppercase italic tracking-wide text-white">Proponer una operación</span>
                            <button type="button" x-on:click="offering = false" class="text-white/60 hover:text-white" aria-label="Cerrar">✕</button>
                        </div>

                        <div class="grid gap-2.5 sm:grid-cols-2">
                            {{-- La operación manda: decide de qué plantilla se
                                 elige jugador y si lo que se pacta es dinero o
                                 plazo. --}}
                            <label class="block sm:col-span-2">
                                <span class="mb-1 block font-mono text-[9px] tracking-[0.14em] text-white/60">OPERACIÓN</span>
                                <select wire:model.live="offerKind" class="{{ $input }}">
                                    @foreach ($this->offerKinds() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="sm:col-span-2">
                                <span class="mb-1 block font-mono text-[9px] tracking-[0.14em] text-white/60">JUGADOR</span>

                                @if ($this->toPresident() && $this->asking())
                                    {{-- Al presidente se le pide del catálogo de EA
                                         FC: se escribe el nombre y se elige. --}}
                                    <div class="relative">
                                        @if ($offerFcId)
                                            <div class="flex items-center justify-between gap-2 rounded-[6px] border border-brand/40 bg-brand/10 px-3.5 py-2.5 text-sm text-white">
                                                <span class="truncate font-medium">{{ $offerFcName }}</span>
                                                <button type="button" wire:click="$set('offerFcId', null)" class="text-white/60 hover:text-white" title="Quitar">✕</button>
                                            </div>
                                        @else
                                            <input type="text" wire:model.live.debounce.300ms="catalogSearch" placeholder="Escribe un nombre: Mbappe, Ekitike…"
                                                   class="{{ $input }}">

                                            @if ($this->catalogResults()->isNotEmpty())
                                                <div class="absolute bottom-full left-0 z-10 mb-1.5 max-h-56 w-full overflow-y-auto rounded-md bg-surface-alt p-1 shadow-xl ring-1 ring-white/10">
                                                    @foreach ($this->catalogResults() as $ficha)
                                                        <button type="button" wire:click="selectCatalog({{ $ficha->fc_id }})" wire:key="fc-{{ $ficha->fc_id }}"
                                                                class="flex w-full items-center justify-between gap-3 rounded-[4px] px-3 py-2 text-left text-sm text-white transition hover:bg-white/[0.07]">
                                                            <span class="truncate">{{ $ficha->nombre() }}</span>
                                                            <span class="shrink-0 font-mono text-[10px] text-white/60">
                                                                <span class="font-bold text-brand">{{ $ficha->overall_rating }}</span> {{ $ficha->position }} · {{ $ficha->club ?? 'sin club' }}
                                                            </span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    {{-- El importe se rellena aquí mismo, en el navegador,
                                         con el valor de mercado del elegido: sin ir al
                                         servidor, que tardaba hasta el siguiente sondeo. --}}
                                    <select wire:model="offerPlayerId" class="{{ $input }}"
                                            x-data="{ values: @js($this->offerableValues()), free: @js($this->free()) }"
                                            x-on:change="if (! free && values[$event.target.value] != null) { $wire.offerAmount = String(values[$event.target.value]) }">
                                        <option value="">{{ $this->asking() ? 'Su jugador…' : 'Tu jugador…' }}</option>
                                        @foreach ($this->offerableOptions() as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>

                            <label class="block">
                                <span class="mb-1 block font-mono text-[9px] tracking-[0.14em] text-white/60">{{ $this->free() ? 'PLAZO' : 'IMPORTE' }}</span>
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
                                        class="w-full -skew-x-12 rounded-[4px] bg-brand px-4 py-2.5 font-display text-sm font-bold uppercase italic tracking-[0.1em] text-black/85 transition hover:opacity-90">
                                    Enviar oferta
                                </button>
                            </div>
                        </div>

                        <p class="mt-3 text-[11px] leading-relaxed text-white/60">
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
                                class="flex h-11 shrink-0 items-center gap-1.5 rounded-[6px] border border-white/10 px-3.5 font-display text-sm font-bold uppercase italic tracking-[0.06em] text-white/75 transition hover:border-brand/50 hover:text-brand"
                                :class="offering ? 'border-brand/50 text-brand' : ''"
                                title="Ofertar por un jugador">
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $offerIcon !!}</svg>
                            <span class="hidden sm:inline">Oferta</span>
                        </button>
                    @endif

                    {{-- Enter envía; Mayúsculas+Enter hace un salto de línea. --}}
                    <textarea wire:model="body" rows="1" maxlength="{{ \App\Models\Message::MAX_LENGTH }}" placeholder="Escribe un mensaje"
                              x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $el.form.requestSubmit(); }"
                              class="max-h-28 min-h-11 w-full resize-none rounded-[6px] border border-white/10 bg-surface px-4 py-2.5 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/15"></textarea>

                    <button type="submit" wire:loading.attr="disabled" wire:target="send"
                            class="flex size-11 shrink-0 -skew-x-12 items-center justify-center rounded-[5px] bg-brand text-black/85 transition hover:opacity-90 disabled:opacity-50"
                            title="Enviar" aria-label="Enviar">
                        <svg class="size-5 skew-x-12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4 21 12 3.4 3.6l-.1 6.5L15 12 3.3 13.9l.1 6.5Z"/></svg>
                    </button>
                </form>
            </div>
        @endif
    </section>
</div>
