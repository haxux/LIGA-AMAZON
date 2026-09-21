<x-filament-panels::page>
    {{-- Sondeo, no websockets (design D11): este despliegue escala a cero y no
         tiene un proceso permanente donde sostenerlos. --}}
    <div class="grid gap-4 lg:grid-cols-[18rem_1fr]" wire:poll.5s>
        <aside class="space-y-4">
            <x-filament::section>
                <x-slot name="heading">Conversaciones</x-slot>

                @forelse ($this->conversations() as $conversation)
                    @php($other = $conversation->other(auth()->user()))
                    @php($unread = $conversation->unreadFor(auth()->user()))

                    <button type="button" wire:click="open({{ $conversation->id }})"
                            class="mb-1 flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm
                                   {{ $conversationId === $conversation->id ? 'bg-primary-500/10 font-semibold' : 'hover:bg-gray-500/10' }}">
                        <span class="truncate">{{ $this->displayName($other) }}</span>
                        @if ($unread > 0)
                            <span class="rounded-full bg-primary-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $unread }}</span>
                        @endif
                    </button>
                @empty
                    <p class="text-sm text-gray-500">Todavía no has hablado con nadie.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section collapsible collapsed>
                <x-slot name="heading">Empezar una conversación</x-slot>

                @foreach ($this->contacts() as $contact)
                    <button type="button" wire:click="openWith({{ $contact->id }})"
                            class="mb-1 block w-full truncate rounded-lg px-3 py-2 text-left text-sm hover:bg-gray-500/10">
                        {{ $this->displayName($contact) }}
                    </button>
                @endforeach
            </x-filament::section>
        </aside>

        @php($conversation = $this->conversation())

        <section>
            @if ($conversation === null)
                <x-filament::section>
                    <p class="text-sm text-gray-500">Elige una conversación, o empieza una nueva.</p>
                </x-filament::section>
            @else
                <x-filament::section>
                    <x-slot name="heading">{{ $this->displayName($conversation->other(auth()->user())) }}</x-slot>

                    <div class="mb-4 max-h-[26rem] space-y-3 overflow-y-auto pr-1">
                        @foreach ($conversation->timeline() as $entry)
                            @if ($entry instanceof \App\Models\Offer)
                                {{-- La oferta se lee entre los mensajes porque ahí es donde se hizo. --}}
                                <div class="rounded-lg border border-amber-500/40 bg-amber-500/5 p-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-amber-600">
                                        Oferta · {{ \App\Models\Offer::STATUSES[$entry->status] }}
                                    </div>
                                    <div class="mt-1 text-sm">
                                        <span class="font-semibold">{{ $entry->player?->name }}</span>
                                        por <span class="font-semibold">{{ number_format($entry->amount, 0, ',', '.') }}</span>
                                        <span class="text-gray-500">— la propone {{ $entry->fromClub?->name }}</span>
                                    </div>

                                    @if ($this->mayAnswer($entry))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <x-filament::button size="xs" color="success" wire:click="acceptOffer({{ $entry->id }})">Aceptar</x-filament::button>
                                            <x-filament::button size="xs" color="danger" wire:click="rejectOffer({{ $entry->id }})">Rechazar</x-filament::button>
                                            <x-filament::button size="xs" color="gray" wire:click="startCounter({{ $entry->id }})">Negociar</x-filament::button>
                                        </div>

                                        @if ($counteringOfferId === $entry->id)
                                            <div class="mt-2 flex flex-wrap items-end gap-2">
                                                <input type="number" min="1" wire:model="counterAmount" placeholder="Contraoferta"
                                                       class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-white/5">
                                                <x-filament::button size="xs" wire:click="sendCounter">Enviar contraoferta</x-filament::button>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @else
                                @php($mine = (int) $entry->user_id === (int) auth()->id())
                                <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[80%] rounded-lg px-3 py-2 text-sm {{ $mine ? 'bg-primary-600 text-white' : 'bg-gray-500/10' }}">
                                        <div class="whitespace-pre-line">{{ $entry->body }}</div>
                                        <div class="mt-1 text-[10px] opacity-60">{{ $entry->created_at?->format('d/m H:i') }}</div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <form wire:submit.prevent="send" class="flex items-end gap-2">
                        <textarea wire:model="body" rows="2" placeholder="Escribe un mensaje"
                                  class="w-full rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-white/5"></textarea>
                        <x-filament::button type="submit">Enviar</x-filament::button>
                    </form>
                </x-filament::section>

                @if ($this->mayOffer())
                    <x-filament::section class="mt-4">
                        <x-slot name="heading">Ofertar por un jugador</x-slot>
                        <x-slot name="description">
                            Aceptar una oferta no mueve nada por sí solo: queda acordada y el administrador
                            registra el traspaso.
                        </x-slot>

                        <div class="flex flex-wrap items-end gap-2">
                            <select wire:model="offerPlayerId"
                                    class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-white/5">
                                <option value="">Jugador…</option>
                                @foreach ($this->offerableOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>

                            <input type="number" min="1" wire:model="offerAmount" placeholder="Importe"
                                   class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-white/5">

                            <x-filament::button wire:click="sendOffer">Enviar oferta</x-filament::button>
                        </div>
                    </x-filament::section>
                @endif
            @endif
        </section>
    </div>
</x-filament-panels::page>
