{{-- Separadas por competición (Fase 15, decisión del propietario): primero la
     temporada entera y después cada competición que el club disputa. Todas a la
     vez y no con un selector, porque lo que se quiere ver de un vistazo es
     cuánto de lo hecho es de liga y cuánto de copa. --}}
@foreach ($statBlocks as $block)
    @php
        $stats = $block['stats'];

        $cells = [
            'PJ' => $stats->played,
            'G' => $stats->won,
            'E' => $stats->drawn,
            'P' => $stats->lost,
            'GF' => $stats->goals_for,
            'GC' => $stats->goals_against,
            'DG' => $stats->goal_difference,
        ];

        // Los puntos sólo se cuentan donde hay tabla. En una copa a eliminatoria
        // no significan nada, y en «General» tampoco, porque sumaría partidos
        // que no dan puntos.
        if ($block['competition']->isLeague()) {
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

    <section @class(['mt-8' => ! $loop->first])>
        {{-- Sin copas no hay nada que separar, y entonces tampoco hay rótulo que
             poner: la pestaña queda exactamente como era. --}}
        @if ($block['title'])
            <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">
                {{ mb_strtoupper($block['title']) }}
            </h2>
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
    </section>
@endforeach
