@props(['lineup', 'team'])

@php
    // El hueco es lo guardado; el reparto en líneas sale de la formación, en
    // Lineup, que es de donde también lo toma el panel del técnico.
    $players = $lineup->slots->keyBy('slot');
    // La pertenencia entera y no sólo el dorsal: de ahí sale también quién
    // lleva el brazalete. Esta vista la ve TODO EL MUNDO —el editor en vivo
    // sólo lo ve el técnico de ese club—, así que lo que se enseñe aquí tiene
    // que ser lo mismo, o el visitante ve una versión pobre sin saberlo.
    $memberships = $team->memberships()->get()->keyBy('player_id');
@endphp

<div class="rounded-xl bg-emerald-800/90 p-4 ring-1 ring-emerald-900/40">
    <div class="mb-3 font-mono text-[10px] tracking-[0.14em] text-white/60">{{ $lineup->formation }}</div>

    {{-- Las líneas se dibujan de atrás hacia delante, con el portero abajo. --}}
    <div class="flex flex-col-reverse gap-4">
        @foreach ($lineup->rows() as $row)
            <div class="flex justify-around gap-2">
                @foreach ($row as $slot)
                    @php($player = $players[$slot]?->player ?? null)

                    @php($membership = $player ? ($memberships[$player->id] ?? null) : null)

                    <div class="flex w-24 flex-col items-center gap-1 rounded-lg bg-black/25 p-2 text-white">
                        <span class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/15 font-display text-sm font-semibold">
                            @if ($player?->photo_path)
                                <img src="{{ Storage::disk(config('filesystems.uploads'))->url($player->photo_path) }}"
                                     alt="" class="size-full rounded-full object-cover">
                            @else
                                {{ $membership?->shirt_number ?? '—' }}
                            @endif
                        </span>

                        <span class="w-full truncate text-center font-display text-[11px] leading-tight">
                            {{ $player?->name ?? 'Vacío' }}@if ($membership?->is_captain) (c)@endif
                        </span>

                        @if ($player)
                            @php($detalle = collect([$player->specific_position, $membership?->shirt_number !== null ? '#'.$membership->shirt_number : null])->filter()->implode(' · '))
                            @if ($detalle !== '')
                                <span class="font-mono text-[9px] tracking-[0.1em] text-white/60">{{ $detalle }}</span>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
