@php
    $cells = [
        'PJ' => $stats->played,
        'G' => $stats->won,
        'E' => $stats->drawn,
        'P' => $stats->lost,
        'GF' => $stats->goals_for,
        'GC' => $stats->goals_against,
        'DG' => $stats->goal_difference,
        'PTS' => $stats->points,
    ];

    // Tarjetas y porterías a cero salen de `game_events`, que las registra
    // desde 2026-09-20; el resto, de los marcadores (design D12).
    $discipline = [
        'AMARILLAS' => $stats->yellow_cards,
        'ROJAS' => $stats->red_cards,
        'PORTERÍAS A CERO' => $stats->clean_sheets,
    ];
@endphp

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
