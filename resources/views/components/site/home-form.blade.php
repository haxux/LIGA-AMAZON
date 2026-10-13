@props(['teams'])

{{-- Los equipos en mejor racha, con la tira de sus últimos partidos.

     Los colores son los de estado que ya tiene el sitio para victoria, empate y
     derrota (--color-win, --color-draw, --color-loss), y van SIEMPRE con su
     letra. No es adorno: medido con el validador, derrota y empate se separan
     sólo ΔE 5,0 en visión deuteranope, o sea que quien no distingue rojo y
     verde los ve casi iguales. La letra es lo que lo salva, y por eso no se
     puede quitar. El blanco sobre los tres pasa de sobra (5,3 · 6,7 · 7,6
     contra el 4,5 que pide un texto de este tamaño). --}}
<section class="overflow-hidden rounded-md bg-surface">
    <h3 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">En forma</h3>

    @if ($teams->isEmpty())
        <p class="px-4 py-5 text-sm text-white/55">Sin partidos jugados todavía.</p>
    @else
        <ol class="divide-y divide-white/[0.055]">
            @foreach ($teams as $row)
                <li class="flex items-center gap-3 px-4 py-3">
                    <x-site.team-crest :team="$row['team']" size="size-6" />

                    <span class="min-w-0 flex-1 truncate font-display text-[19px] font-semibold tracking-[0.02em] text-white">
                        {{ $row['team']->name }}
                    </span>

                    {{-- Del más antiguo al más reciente, que es como se lee una
                         racha; el servicio los da al revés. --}}
                    <span class="flex shrink-0 gap-1">
                        @foreach (array_reverse($row['results']) as $result)
                            @php($tono = ['V' => 'bg-win text-white', 'E' => 'bg-draw text-white', 'D' => 'bg-loss text-white'][$result])
                            <span class="flex size-5 items-center justify-center rounded-[3px] font-mono text-[9px] font-bold {{ $tono }}"
                                  title="{{ ['V' => 'Victoria', 'E' => 'Empate', 'D' => 'Derrota'][$result] }}">{{ $result }}</span>
                        @endforeach
                    </span>

                    <span class="w-7 shrink-0 text-right font-display text-lg font-bold text-brand">{{ $row['points'] }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</section>
