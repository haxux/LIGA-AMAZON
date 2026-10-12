@props(['ficha', 'esPortero' => false])

@php
    // EA REUTILIZA las seis casillas en los porteros, y no significan lo
    // mismo: lo que en un delantero es velocidad, en un portero es estirada.
    // Se comprobó con datos reales — Courtois sale 87/89/78/90/46/90, y ese 46
    // es su velocidad, no su defensa. Enseñarlas con las etiquetas de campo
    // sería mentir con seis números a la vez.
    $casillas = $esPortero
        ? ['ESTIRADA' => 'pace', 'PARADA' => 'shooting', 'SAQUE' => 'passing', 'REFLEJOS' => 'dribbling', 'VELOCIDAD' => 'defending', 'COLOCACIÓN' => 'physicality']
        : ['RITMO' => 'pace', 'TIRO' => 'shooting', 'PASE' => 'passing', 'REGATE' => 'dribbling', 'DEFENSA' => 'defending', 'FÍSICO' => 'physicality'];

    $estrellas = fn (?int $n) => $n === null ? null : [
        'llenas' => $n,
        'vacias' => max(0, 5 - $n),
    ];

    $pie = ['Right' => 'Diestro', 'Left' => 'Zurdo'][$ficha->preferred_foot] ?? $ficha->preferred_foot;
@endphp

{{--
    La magnitud se mide UNA vez: la longitud de la barra. El número va en tinta
    normal y no en color por tramos, que sería medir lo mismo dos veces y además
    obliga a recordar qué significa cada color. La barra se lee de un vistazo y
    sin leyenda.

    Todo en el dorado de la marca, que es el único acento del sitio. Comprobado
    con el validador de contraste contra este fondo: pasa.
--}}
<section class="mb-6">
    <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">
        VALORACIÓN {{ mb_strtoupper($ficha->edition ?? 'EA FC') }}
    </h2>

    <div class="overflow-hidden rounded-[6px] bg-surface-alt">
        <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:gap-7">
            {{-- La media: la cifra que todo el mundo busca primero, y la única
                 que va en color. --}}
            <div class="flex shrink-0 items-center gap-3 sm:flex-col sm:gap-0">
                <span class="font-display text-[56px] leading-none font-bold text-brand">
                    {{ $ficha->overall_rating ?? '—' }}
                </span>
                <span class="font-mono text-[9px] tracking-[0.16em] text-white/40 sm:mt-1">MEDIA</span>
            </div>

            <div class="hidden w-px self-stretch bg-white/[0.07] sm:block"></div>

            {{-- Las seis, cada una con su medidor. La barra encima del número
                 porque lo que se compara de un vistazo son las longitudes. --}}
            <div class="grid flex-1 grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                @foreach ($casillas as $etiqueta => $campo)
                    @php($valor = $ficha->$campo)
                    <div>
                        <div class="mb-1 flex items-baseline justify-between gap-2">
                            <span class="font-mono text-[9px] tracking-[0.12em] text-white/45">{{ $etiqueta }}</span>
                            <span class="font-display text-lg leading-none font-bold text-white">{{ $valor ?? '—' }}</span>
                        </div>
                        <div class="h-[5px] overflow-hidden rounded-full bg-white/[0.08]">
                            @if ($valor !== null)
                                <div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $valor)) }}%"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-white/[0.07] px-5 py-3">
            @if ($pie)
                <span class="font-mono text-[10px] tracking-[0.1em] text-white/45">
                    PIE <span class="text-white/80">{{ mb_strtoupper($pie) }}</span>
                </span>
            @endif

            @foreach (['PIE MALO' => $ficha->weak_foot, 'FILIGRANAS' => $ficha->skill_moves] as $titulo => $cuantas)
                @if ($cuantas)
                    @php($e = $estrellas($cuantas))
                    <span class="font-mono text-[10px] tracking-[0.1em] text-white/45">
                        {{ $titulo }}
                        {{-- Las llenas en el dorado de la marca, que es lo que
                             las hace saltar; las vacías apenas se insinúan. --}}
                        <span class="ml-1 text-brand">{{ str_repeat('★', $e['llenas']) }}</span><span class="text-white/15">{{ str_repeat('★', $e['vacias']) }}</span>
                    </span>
                @endif
            @endforeach

            @if ($ficha->nationality)
                <span class="font-mono text-[10px] tracking-[0.1em] text-white/45">
                    {{ mb_strtoupper($ficha->nationality) }}
                </span>
            @endif
        </div>
    </div>
</section>
