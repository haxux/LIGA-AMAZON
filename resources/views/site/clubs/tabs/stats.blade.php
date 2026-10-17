{{-- Separadas por competición (Fase 15, decisión del propietario): primero la
     temporada entera y después cada competición que el club disputa. Todas a la
     vez y no con un selector, porque lo que se quiere ver de un vistazo es
     cuánto de lo hecho es de liga y cuánto de copa. --}}
@foreach ($statBlocks as $block)
    @php
        $stats = $block['stats'];

        // Cada cifra con su sigla y su nombre entero: la sigla es lo que se
        // lee de un vistazo y el nombre, lo que explica qué es.
        $results = [
            'PJ' => ['Partidos jugados', $stats->played],
            'G' => ['Ganados', $stats->won],
            'E' => ['Empatados', $stats->drawn],
            'P' => ['Perdidos', $stats->lost],
            'GF' => ['Goles a favor', $stats->goals_for],
            'GC' => ['Goles en contra', $stats->goals_against],
            'DG' => ['Diferencia de goles', $stats->goal_difference],
        ];

        // Los puntos sólo se cuentan donde hay tabla. En una copa a eliminatoria
        // no significan nada, y en «General» tampoco, porque sumaría partidos
        // que no dan puntos.
        if ($block['competition']->isLeague()) {
            $results['PTS'] = ['Puntos', $stats->points];
        }

        // Tarjetas y porterías a cero salen de `game_events`, que las registra
        // desde 2026-09-20; el resto, de los marcadores (design D12).
        $discipline = [
            'AMARILLAS' => $stats->yellow_cards,
            'ROJAS' => $stats->red_cards,
            'PORTERÍAS A CERO' => $stats->clean_sheets,
            'MVP DEL PARTIDO' => $stats->mvps,
        ];

        // El Week MVP es de la jornada, que sólo existe en la liga: en una copa
        // no se enseña.
        if ($block['competition']->isAll() || $block['competition']->isLeague()) {
            $discipline['WEEK MVP'] = $stats->week_mvps;
        }
    @endphp

    <section @class(['mt-8' => ! $loop->first])>
        {{-- Sin copas no hay nada que separar, y entonces tampoco hay rótulo que
             poner: la pestaña queda exactamente como era. --}}
        @if ($block['title'])
            <h2 class="mb-3 inline-block rounded-[4px] bg-surface-alt px-3 py-1.5 font-mono text-[10px] tracking-[0.14em] text-brand">
                {{ mb_strtoupper($block['title']) }}
            </h2>
        @endif

        {{-- Dos tablas medianas lado a lado: lo que se jugó y lo que se
             sancionó. Una fila por dato, con la cifra siempre en la misma
             columna. --}}
        <div class="grid items-start gap-3 md:grid-cols-[3fr_2fr]">
            <table class="w-full table-fixed overflow-hidden rounded-[5px] bg-surface text-left">
                <colgroup>
                    <col class="w-16">
                    <col>
                    <col class="w-20">
                </colgroup>
                <thead>
                    <tr class="bg-surface-alt font-mono text-[9px] tracking-[0.14em] text-white/40">
                        <th class="py-2 pl-4 pr-2">DATO</th>
                        <th class="px-2 py-2">DETALLE</th>
                        <th class="py-2 pl-2 pr-4 text-right">TOTAL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.055]">
                    @foreach ($results as $label => [$name, $value])
                        <tr>
                            <td class="py-1.5 pl-4 pr-2 font-mono text-[11px] tracking-[0.1em] {{ $label === 'PTS' ? 'text-brand' : 'text-white/55' }}"><span>{{ $label }}</span></td>
                            <td class="truncate px-2 py-1.5 font-display text-[15px] text-white/80">{{ $name }}</td>
                            <td class="py-1.5 pl-2 pr-4 text-right font-display text-lg font-bold tabular-nums {{ $label === 'PTS' ? 'text-brand' : 'text-white' }}">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="w-full table-fixed overflow-hidden rounded-[5px] bg-surface text-left">
                <colgroup>
                    <col>
                    <col class="w-20">
                </colgroup>
                <thead>
                    <tr class="bg-surface-alt font-mono text-[9px] tracking-[0.14em] text-white/40">
                        <th class="py-2 pl-4 pr-2">DISCIPLINA Y PREMIOS</th>
                        <th class="py-2 pl-2 pr-4 text-right">TOTAL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.055]">
                    @foreach ($discipline as $label => $value)
                        <tr>
                            <td class="truncate py-1.5 pl-4 pr-2 font-mono text-[11px] tracking-[0.1em] text-white/55">{{ $label }}</td>
                            <td class="py-1.5 pl-2 pr-4 text-right font-display text-lg font-bold tabular-nums text-white">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endforeach
