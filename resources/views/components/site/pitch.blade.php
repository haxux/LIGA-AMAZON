@props(['lineup', 'team'])

@php
    // El hueco es lo guardado; el reparto en líneas sale de la formación, en
    // Lineup, que es de donde también lo toma el panel del técnico.
    $players = $lineup->slots->keyBy('slot');
    $numbers = $team
        ->memberships()
        ->pluck('shirt_number', 'player_id');
@endphp

<div class="rounded-xl bg-emerald-800/90 p-4 ring-1 ring-emerald-900/40">
    <div class="mb-3 font-mono text-[10px] tracking-[0.14em] text-white/60">{{ $lineup->formation }}</div>

    {{-- Las líneas se dibujan de atrás hacia delante, con el portero abajo. --}}
    <div class="flex flex-col-reverse gap-4">
        @foreach ($lineup->rows() as $row)
            <div class="flex justify-around gap-2">
                @foreach ($row as $slot)
                    @php($player = $players[$slot]?->player ?? null)

                    <div class="flex w-24 flex-col items-center gap-1 rounded-lg bg-black/25 p-2 text-white">
                        <span class="flex size-8 items-center justify-center rounded-full bg-white/15 font-display text-sm font-semibold">
                            {{ $player ? ($numbers[$player->id] ?? '—') : '—' }}
                        </span>
                        <span class="w-full truncate text-center font-display text-[11px] leading-tight">
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
