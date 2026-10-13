@props(['highlights'])

{{-- Cuatro cifras sueltas de la liga, cada una un mosaico y ninguna un gráfico:
     cuando la historia es un número, el número ES el gráfico. Sin barras de una
     sola barra ni quesitos de dos trozos. --}}
@php
    $tiles = array_values(array_filter([
        $highlights['goals'] > 0 ? ['label' => 'Goles en la liga', 'value' => number_format($highlights['goals']), 'foot' => null] : null,
        $highlights['average'] !== null ? ['label' => 'Goles por partido', 'value' => number_format($highlights['average'], 2), 'foot' => null] : null,
        $highlights['topScoring'] ? [
            'label' => 'El más goleador',
            'value' => $highlights['topScoringGoals'],
            'foot' => $highlights['topScoring']->name,
        ] : null,
        $highlights['biggest'] ? [
            'label' => 'Mayor goleada',
            'value' => $highlights['biggest']->home_score.'–'.$highlights['biggest']->away_score,
            'foot' => $highlights['biggest']->homeTeam->name.' · '.$highlights['biggest']->awayTeam->name,
        ] : null,
    ]));
@endphp

@if ($tiles !== [])
    <section class="overflow-hidden rounded-md bg-surface">
        <h3 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">La temporada en cifras</h3>

        <div class="grid grid-cols-2 gap-px bg-white/[0.055]">
            @foreach ($tiles as $tile)
                <div class="bg-surface p-4">
                    <div class="mb-1 font-mono text-[10px] tracking-[0.12em] text-white/45">
                        {{ mb_strtoupper($tile['label']) }}
                    </div>
                    <div class="font-display text-[32px] leading-none font-bold text-white">{{ $tile['value'] }}</div>
                    @if ($tile['foot'])
                        <div class="mt-1 truncate font-mono text-[10px] text-white/40" title="{{ $tile['foot'] }}">{{ $tile['foot'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
