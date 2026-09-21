@php
    $cells = [
        'PJ' => $stats->played,
        'G' => $stats->won,
        'E' => $stats->drawn,
        'P' => $stats->lost,
        'GF' => $stats->goals_for,
        'GC' => $stats->goals_against,
        'DG' => $stats->goal_difference,
    ];

    // Los puntos sólo se cuentan donde hay tabla. En una copa a eliminatoria no
    // significan nada, y mostrarlos ahí sería inventarse una clasificación; con
    // «Todo» tampoco, porque sumaría partidos que no dan puntos.
    if ($competition->isLeague()) {
        $cells['PTS'] = $stats->points;
    }

    // Tarjetas y porterías a cero salen de `game_events`, que las registra
    // desde 2026-09-20; el resto, de los marcadores (design D12).
    $discipline = [
        'AMARILLAS' => $stats->yellow_cards,
        'ROJAS' => $stats->red_cards,
        'PORTERÍAS A CERO' => $stats->clean_sheets,
    ];
@endphp

{{-- Separadas por competición (Fase 15). Un club que no juega ninguna copa
     sólo tendría «Todo» y «Liga», que dicen lo mismo: no hay nada que elegir. --}}
@if (count($competitionOptions) > 2)
    <form method="GET" action="{{ route('site.clubs.show', ['club' => $club->id, 'tab' => 'stats']) }}"
          class="mb-4 flex flex-wrap items-end gap-4 rounded-md bg-surface p-4">
        {{-- La temporada elegida viaja escondida: el selector de arriba es otro
             formulario, y sin esto cambiar de competición devolvería a la vigente. --}}
        @if ($selectedSeason)
            <input type="hidden" name="temporada" value="{{ $selectedSeason->id }}">
        @endif

        <x-site.filter-select name="competicion" label="COMPETICIÓN"
                              :options="$competitionOptions" :selected="$competition->key" />

        <noscript>
            <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
        </noscript>
    </form>
@endif

<div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
    @foreach ($cells as $label => $value)
        <div class="rounded-[5px] bg-surface-alt p-4 text-center">
            <span class="block font-mono text-[9px] tracking-[0.14em] text-white/40">{{ $label }}</span>
            <span class="font-display text-3xl font-bold {{ $label === 'PTS' ? 'text-brand' : 'text-white' }}">{{ $value }}</span>
        </div>
    @endforeach
</div>

<div class="mt-3 grid gap-2 sm:grid-cols-3">
    @foreach ($discipline as $label => $value)
        <div class="rounded-[5px] bg-surface-alt p-4 text-center">
            <span class="block font-mono text-[9px] tracking-[0.14em] text-white/40">{{ $label }}</span>
            <span class="font-display text-3xl font-bold text-white">{{ $value }}</span>
        </div>
    @endforeach
</div>
