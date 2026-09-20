<x-filament-panels::page>
    @php($team = $this->team())

    @if ($team === null)
        <x-filament::section>
            <p class="text-sm">
                Este club todavía no está inscrito en la temporada vigente, así que no hay
                plantilla con la que armar el once.
            </p>
        </x-filament::section>
    @else
        @php($squad = $this->squad())

        <x-filament::section>
            <div class="flex flex-wrap items-end gap-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium">Formación</span>
                    <select wire:model.live="formation"
                            class="rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-white/5">
                        @foreach ($this->formationOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <x-filament::button wire:click="save" wire:loading.attr="disabled">
                    Guardar once
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- El campo. Las líneas salen de la formación, y cada hueco es un
             selector: sin fotos, la tarjeta muestra el dorsal y el nombre. --}}
        <div class="mt-6 rounded-xl bg-emerald-800/90 p-4 ring-1 ring-emerald-900/40">
            <div class="flex flex-col-reverse gap-4">
                @foreach ($this->rows() as $row)
                    <div class="flex justify-around gap-2">
                        @foreach ($row as $slot)
                            @php($playerId = $this->picks[$slot] ?? null)
                            @php($player = $playerId ? $squad->firstWhere('id', (int) $playerId) : null)

                            <div class="flex w-28 flex-col items-center gap-1 rounded-lg bg-black/25 p-2 text-white">
                                <span class="flex size-9 items-center justify-center rounded-full bg-white/15 font-semibold">
                                    {{ $player?->shirt_number ?? '—' }}
                                </span>
                                <select wire:model.live="picks.{{ $slot }}"
                                        class="w-full rounded border-0 bg-black/30 py-1 text-[11px] text-white">
                                    <option value="">Vacío</option>
                                    @foreach ($squad as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </select>
                                @if ($player?->specific_position)
                                    <span class="text-[10px] uppercase tracking-wide text-white/70">{{ $player->specific_position }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
