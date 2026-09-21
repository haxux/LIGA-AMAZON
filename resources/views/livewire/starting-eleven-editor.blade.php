@php
    $placed = collect($this->picks)->filter()->map(fn ($id) => (int) $id);
@endphp

<div>
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2">
            <span class="font-mono text-[10px] tracking-[0.12em] text-white/50">FORMACIÓN</span>
            <select wire:model.live="formation"
                    class="rounded-[4px] border border-white/10 bg-surface px-3 py-1.5 font-display text-sm font-semibold uppercase tracking-[0.06em] text-white">
                @foreach ($this->formationOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <button type="button" wire:click="save"
                class="rounded-[4px] bg-brand px-4 py-1.5 font-display text-sm font-bold uppercase tracking-[0.08em] text-ink hover:opacity-90">
            Guardar once
        </button>

        @if ($saved)
            <span class="font-mono text-[10px] tracking-[0.12em] text-emerald-400">GUARDADO</span>
        @endif

        @if ($error)
            <span class="font-mono text-[10px] tracking-[0.12em] text-rose-400">{{ mb_strtoupper($error) }}</span>
        @endif
    </div>

    {{-- El campo. Las líneas salen de la formación y cada hueco es un botón:
         pulsarlo abre la plantilla y elegir coloca ahí a ese jugador. --}}
    <div class="rounded-xl bg-emerald-800/90 p-4 ring-1 ring-emerald-900/40">
        <div class="mb-3 font-mono text-[10px] tracking-[0.14em] text-white/60">{{ $formation }}</div>

        <div class="flex flex-col-reverse gap-4">
            {{-- `wire:key` en las dos vueltas: los once huecos son idénticos, y
                 sin clave Livewire los empareja por posición al re-renderizar y
                 el valor de uno acaba en otro. --}}
            @foreach ($this->rows() as $line => $row)
                <div class="flex justify-around gap-2" wire:key="linea-{{ $formation }}-{{ $line }}">
                    @foreach ($row as $slot)
                        @php($playerId = $this->picks[$slot] ?? null)
                        @php($player = $playerId ? $squad->firstWhere('id', (int) $playerId) : null)

                        <div class="relative flex w-20 flex-col items-center gap-1 sm:w-24" wire:key="hueco-{{ $slot }}">
                            <button type="button" wire:click="openPicker({{ $slot }})"
                                    class="flex size-11 items-center justify-center rounded-full font-display text-base font-bold
                                           {{ $player ? 'bg-brand text-ink' : 'border border-dashed border-white/40 bg-black/20 text-white/50' }}
                                           {{ $openSlot === $slot ? 'ring-2 ring-white' : '' }}">
                                {{ $player?->shirt_number ?? '+' }}
                            </button>

                            <span class="w-full truncate text-center font-display text-[11px] leading-tight text-white">
                                {{ $player?->name ?? 'Vacío' }}
                            </span>

                            @if ($player?->specific_position)
                                <span class="font-mono text-[9px] tracking-[0.1em] text-white/60">{{ $player->specific_position }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    @if ($openSlot !== null)
        {{-- La plantilla, para el hueco abierto. Los ya colocados siguen en la
             lista y marcados: elegirlos los mueve aquí, que es como se cambia
             a dos jugadores de sitio. --}}
        <div class="mt-3 rounded-[6px] bg-surface-alt p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <span class="font-mono text-[10px] tracking-[0.12em] text-brand">HUECO {{ $openSlot }}</span>

                <span class="flex gap-2">
                    <button type="button" wire:click="place({{ $openSlot }})"
                            class="rounded-[4px] border border-white/15 px-3 py-1 font-mono text-[10px] tracking-[0.1em] text-white/70 hover:bg-white/5">
                        VACIAR
                    </button>
                    <button type="button" wire:click="closePicker"
                            class="rounded-[4px] border border-white/15 px-3 py-1 font-mono text-[10px] tracking-[0.1em] text-white/70 hover:bg-white/5">
                        CERRAR
                    </button>
                </span>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($squad as $option)
                    @php($isPlaced = $placed->contains($option->id))

                    <button type="button" wire:click="place({{ $openSlot }}, {{ $option->id }})" wire:key="opcion-{{ $option->id }}"
                            class="flex items-center gap-3 rounded-[5px] p-2 text-left hover:bg-surface-muted {{ $isPlaced ? 'opacity-60' : '' }}">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-[3px] bg-surface-muted font-display text-sm font-bold text-brand">
                            {{ $option->shirt_number ?? '—' }}
                        </span>

                        <span class="min-w-0">
                            <span class="block truncate font-display text-sm font-semibold text-white">{{ $option->name }}</span>
                            <span class="block font-mono text-[9px] tracking-[0.1em] text-white/45">
                                {{ $option->specific_position ?? $option->position }}@if ($isPlaced) · YA ALINEADO @endif
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>

            @if ($squad->isEmpty())
                <p class="font-mono text-[11px] tracking-[0.08em] text-white/50">
                    Este club todavía no tiene plantilla inscrita en esta temporada.
                </p>
            @endif
        </div>
    @endif
</div>
