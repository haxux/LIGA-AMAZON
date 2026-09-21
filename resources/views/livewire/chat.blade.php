@php
    use App\Models\Offer;
    use Illuminate\Support\Str;

    $me = $this->user();
    $conversation = $this->conversation();
    $other = $conversation?->other($me);
@endphp

{{-- Sondeo, no websockets (design D11): este despliegue escala a cero y no
     tiene un proceso permanente donde sostenerlos. --}}
<div class="grid gap-4 md:grid-cols-[17rem_1fr] xl:grid-cols-[21rem_1fr]" wire:poll.5s>

    {{-- ─── Los hilos ─────────────────────────────────────────────────── --}}
    <aside class="flex h-[32rem] flex-col overflow-hidden rounded-[6px] bg-surface md:h-[36rem]"
           x-data="{ starting: false }">
        <header class="flex items-center justify-between gap-2 bg-surface-alt px-4 py-3">
            <h2 class="font-display text-lg uppercase tracking-wide text-brand">Chats</h2>

            <button type="button" x-on:click="starting = ! starting"
                    class="flex size-7 items-center justify-center rounded-full bg-brand font-display text-lg leading-none text-ink hover:opacity-90"
                    title="Empezar una conversación">
                <span x-text="starting ? '×' : '+'"></span>
            </button>
        </header>

        {{-- El listín, que se despliega desde el botón de arriba. --}}
        <div x-show="starting" x-cloak class="max-h-56 overflow-y-auto bg-surface-alt/60">
            @foreach ($this->contacts() as $contact)
                <button type="button" wire:click="openWith({{ $contact->id }})" x-on:click="starting = false"
                        wire:key="contacto-{{ $contact->id }}"
                        class="flex w-full items-center gap-3 px-4 py-2 text-left hover:bg-surface-muted">
                    <x-chat.avatar :user="$contact" size="size-8" text="text-xs" />
                    <span class="min-w-0 flex-1 truncate font-display text-sm text-white/80">{{ $this->displayName($contact) }}</span>
                </button>
            @endforeach
        </div>

        <div class="flex-1 overflow-y-auto">
            @forelse ($this->conversations() as $thread)
                @php($with = $thread->other($me))
                @php($unread = $thread->unreadFor($me))
                @php($last = $thread->messages->first())
                @php($isOpen = $conversation?->is($thread))

                <button type="button" wire:click="open({{ $thread->id }})" wire:key="hilo-{{ $thread->id }}"
                        class="flex w-full items-center gap-3 border-b border-white/[0.055] px-4 py-3 text-left
                               {{ $isOpen ? 'bg-surface-alt' : 'hover:bg-surface-alt/60' }}">
                    <x-chat.avatar :user="$with" />

                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="truncate font-display text-base font-semibold text-white">{{ $with?->name }}</span>
                            @if ($last)
                                <span class="shrink-0 font-mono text-[10px] text-white/40">{{ $last->created_at?->format('H:i') }}</span>
                            @endif
                        </span>

                        <span class="flex items-center justify-between gap-2">
                            <span class="truncate text-xs text-white/50">
                                {{ $last ? Str::limit($last->body, 32) : $this->subtitleFor($with) }}
                            </span>

                            @if ($unread > 0)
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 font-display text-[11px] font-bold text-ink">
                                    {{ $unread }}
                                </span>
                            @endif
                        </span>
                    </span>
                </button>
            @empty
                <p class="px-4 py-8 text-center text-sm text-white/50">
                    Todavía no has hablado con nadie.<br>
                    Pulsa <span class="font-display text-brand">+</span> para empezar.
                </p>
            @endforelse
        </div>
    </aside>

    {{-- ─── El hilo abierto ───────────────────────────────────────────── --}}
    <section class="flex h-[32rem] flex-col overflow-hidden rounded-[6px] bg-surface md:h-[36rem]">
        @if ($conversation === null)
            <div class="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                <span class="flex size-16 items-center justify-center rounded-full bg-surface-alt text-3xl" aria-hidden="true">💬</span>
                <p class="font-display text-lg uppercase tracking-wide text-white/50">Elige una conversación</p>
                <p class="max-w-xs text-sm text-white/40">Aquí se lee el hilo, y desde aquí se ofertan jugadores a otro técnico.</p>
            </div>
        @else
            <header class="flex items-center gap-3 bg-surface-alt px-4 py-3">
                <x-chat.avatar :user="$other" size="size-10" />

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-lg font-semibold text-white">{{ $other?->name }}</span>
                    <span class="block truncate font-mono text-[10px] tracking-[0.12em] text-white/45">
                        {{ mb_strtoupper($this->subtitleFor($other) ?? '') }}
                    </span>
                </span>

                {{-- En móvil, donde las dos columnas se apilan, hace falta una
                     salida del hilo para volver a la lista. --}}
                <button type="button" wire:click="close"
                        class="rounded-[4px] border border-white/10 px-2 py-1 font-mono text-[10px] tracking-[0.1em] text-white/60 hover:bg-white/5 md:hidden">
                    VOLVER
                </button>
            </header>

            <div class="flex-1 space-y-2 overflow-y-auto px-4 py-4"
                 style="background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px); background-size: 16px 16px;">
                @php($lastDay = null)

                @foreach ($conversation->timeline() as $entry)
                    @php($day = $entry->created_at?->format('d/m/Y'))

                    @if ($day !== $lastDay)
                        @php($lastDay = $day)
                        <div class="flex justify-center py-1">
                            <span class="rounded-full bg-surface-alt px-3 py-1 font-mono text-[10px] uppercase tracking-[0.12em] text-white/50">
                                {{ $entry->created_at?->isToday() ? 'Hoy' : ($entry->created_at?->isYesterday() ? 'Ayer' : $day) }}
                            </span>
                        </div>
                    @endif

                    @if ($entry instanceof Offer)
                        @php($mineOffer = (int) $entry->moved_by === (int) $me?->id)

                        {{-- La oferta se lee entre los mensajes porque ahí es
                             donde se hizo, y del lado de quien la movió. --}}
                        <div class="flex {{ $mineOffer ? 'justify-end' : 'justify-start' }}" wire:key="oferta-{{ $entry->id }}">
                            <div class="max-w-[85%] rounded-[6px] border-l-[3px] border-brand bg-surface-alt p-3">
                                <div class="flex items-center gap-2 font-mono text-[9px] uppercase tracking-[0.14em] text-brand">
                                    <span aria-hidden="true">💰</span>
                                    <span>{{ \App\Models\Offer::KINDS[$entry->kind] ?? 'Oferta' }}</span>
                                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-white/70">{{ Offer::STATUSES[$entry->status] }}</span>
                                </div>

                                <div class="mt-1 font-display text-base text-white">
                                    <span class="font-semibold">{{ $entry->player?->name }}</span>
                                    @if ($entry->isFree())
                                        <span class="text-white/50">en cesión,</span>
                                        <span class="font-bold text-brand">{{ \App\Models\Transfer::LOAN_TERMS[$entry->loan_term] ?? 'sin plazo' }}</span>
                                    @else
                                        <span class="text-white/50">por</span>
                                        <span class="font-bold text-brand">{{ number_format($entry->amount, 0, ',', '.') }}</span>
                                    @endif
                                </div>

                                <div class="mt-0.5 font-mono text-[10px] tracking-[0.08em] text-white/40">
                                    LA PROPONE {{ mb_strtoupper($entry->fromClub?->name ?? '') }} · {{ $entry->created_at?->format('H:i') }}
                                </div>

                                @if ($this->mayAnswer($entry))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button type="button" wire:click="acceptOffer({{ $entry->id }})"
                                                class="rounded-[4px] bg-emerald-600 px-3 py-1 font-display text-xs font-bold uppercase tracking-[0.08em] text-white hover:bg-emerald-500">
                                            Aceptar
                                        </button>
                                        <button type="button" wire:click="rejectOffer({{ $entry->id }})"
                                                class="rounded-[4px] bg-rose-800 px-3 py-1 font-display text-xs font-bold uppercase tracking-[0.08em] text-white hover:bg-rose-700">
                                            Rechazar
                                        </button>
                                        <button type="button" wire:click="startCounter({{ $entry->id }})"
                                                class="rounded-[4px] border border-white/15 px-3 py-1 font-display text-xs font-bold uppercase tracking-[0.08em] text-white/80 hover:bg-white/5">
                                            Negociar
                                        </button>
                                    </div>

                                    @if ($counteringOfferId === $entry->id)
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <input type="number" min="1" wire:model="counterAmount" placeholder="Contraoferta"
                                                   class="w-36 rounded-[4px] border border-white/10 bg-surface px-3 py-1.5 text-sm text-white">
                                            <button type="button" wire:click="sendCounter"
                                                    class="rounded-[4px] bg-brand px-3 py-1.5 font-display text-xs font-bold uppercase tracking-[0.08em] text-ink hover:opacity-90">
                                                Enviar
                                            </button>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @else
                        @php($mine = (int) $entry->user_id === (int) $me?->id)

                        <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}" wire:key="mensaje-{{ $entry->id }}">
                            <div class="max-w-[75%] rounded-[8px] px-3 py-2
                                        {{ $mine ? 'rounded-br-none bg-brand text-ink' : 'rounded-bl-none bg-surface-alt text-white' }}">
                                <div class="whitespace-pre-line text-sm leading-snug">{{ $entry->body }}</div>
                                <div class="mt-1 text-right font-mono text-[9px] {{ $mine ? 'text-ink/60' : 'text-white/40' }}">
                                    {{ $entry->created_at?->format('H:i') }}
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- ─── Barra de escritura ────────────────────────────────── --}}
            <div class="bg-surface-alt px-3 py-3" x-data="{ offering: false }">
                @if ($error)
                    <p class="mb-2 font-mono text-[10px] uppercase tracking-[0.1em] text-rose-400">{{ $error }}</p>
                @endif

                @if ($this->mayOffer())
                    <div x-show="offering" x-cloak class="mb-3 rounded-[6px] border-l-[3px] border-brand bg-surface p-3">
                        <div class="mb-2 font-mono text-[9px] uppercase tracking-[0.14em] text-brand">Proponer una operación</div>

                        <div class="flex flex-wrap items-center gap-2">
                            {{-- La operación manda: decide de qué plantilla se
                                 elige jugador y si lo que se pacta es dinero o
                                 plazo. --}}
                            <select wire:model.live="offerKind"
                                    class="rounded-[4px] border border-white/10 bg-surface-alt px-3 py-2 text-sm text-white">
                                @foreach ($this->offerKinds() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <select wire:model="offerPlayerId"
                                    class="min-w-[11rem] flex-1 rounded-[4px] border border-white/10 bg-surface-alt px-3 py-2 text-sm text-white">
                                <option value="">{{ $this->asking() ? 'Su jugador…' : 'Tu jugador…' }}</option>
                                @foreach ($this->offerableOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>

                            @if ($this->free())
                                <select wire:model="offerTerm"
                                        class="w-36 rounded-[4px] border border-white/10 bg-surface-alt px-3 py-2 text-sm text-white">
                                    <option value="">Plazo…</option>
                                    @foreach ($this->loanTerms() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="number" min="1" wire:model="offerAmount" placeholder="Importe"
                                       class="w-32 rounded-[4px] border border-white/10 bg-surface-alt px-3 py-2 text-sm text-white">
                            @endif

                            <button type="button" wire:click="sendOffer" x-on:click="offering = false"
                                    class="rounded-[4px] bg-brand px-3 py-2 font-display text-xs font-bold uppercase tracking-[0.08em] text-ink hover:opacity-90">
                                Enviar
                            </button>
                        </div>

                        <p class="mt-2 text-[11px] text-white/45">
                            {{ $this->free()
                                ? 'Una cesión no tiene coste: lo que se pacta es el plazo.'
                                : 'Aceptarla no mueve nada por sí sola: queda acordada y el administrador la cierra.' }}
                        </p>
                    </div>
                @endif

                <form wire:submit.prevent="send" class="flex items-end gap-2">
                    @if ($this->mayOffer())
                        <button type="button" x-on:click="offering = ! offering"
                                class="flex size-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-lg hover:bg-white/5"
                                title="Ofertar por un jugador">
                            💰
                        </button>
                    @endif

                    <textarea wire:model="body" rows="1" placeholder="Escribe un mensaje"
                              class="max-h-28 w-full resize-y rounded-[18px] border border-white/10 bg-surface px-4 py-2 text-sm text-white placeholder:text-white/30"></textarea>

                    <button type="submit" wire:loading.attr="disabled"
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-ink hover:opacity-90 disabled:opacity-50"
                            title="Enviar">
                        <span aria-hidden="true">➤</span>
                    </button>
                </form>
            </div>
        @endif
    </section>
</div>
