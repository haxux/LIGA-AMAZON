@props(['heading', 'rows', 'zones' => null, 'form' => null])

{{-- La tabla de una división. El puesto va en una chapa inclinada del color de
     su zona (Champions, ascenso, descenso…), con la raya del borde de la fila
     del mismo color, y la leyenda debajo lo explica. Los puntos tienen su
     columna oscura con la cifra grande.

     En el móvil sólo caben puesto, equipo, PJ, DG y PTS: antes la tabla entera
     no cabía y se cortaba justo por DG y PTS. El resto aparece desde tableta, y
     la racha (`form`, opcional: Historia no la pasa) desde escritorio. --}}
@php
    $zones = collect($zones);
    $form = collect($form);
    $tones = ['V' => 'bg-win', 'E' => 'bg-draw', 'D' => 'bg-loss'];
    $names = ['V' => 'Victoria', 'E' => 'Empate', 'D' => 'Derrota'];
@endphp

<section class="mb-10 overflow-hidden rounded-md bg-surface">
    @if ($heading)
        <h2 class="flex items-center gap-3 bg-surface-alt px-4 py-2.5">
            <span class="grid -skew-x-12 place-items-center rounded-[3px] bg-brand px-2.5 py-1 text-ink" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="size-5 skew-x-12" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                    <path d="M12 2.8l7 2.5V11c0 4.7-3 8.1-7 10-4-1.9-7-5.3-7-10V5.3z" />
                </svg>
            </span>
            <span class="font-display text-lg font-bold uppercase italic tracking-wide text-white">{{ $heading }}</span>
        </h2>
    @endif

    <table class="w-full table-fixed text-left">
        <thead>
            <tr class="font-mono text-[10px] tracking-[0.1em] text-white/50">
                <th class="w-12 py-2 pl-4 sm:w-14">#</th>
                <th class="px-2 py-2">EQUIPO</th>
                <th class="w-10 px-1 py-2 text-center sm:w-12" title="Partidos jugados">PJ</th>
                <th class="hidden w-10 px-1 py-2 text-center sm:table-cell" title="Ganados">G</th>
                <th class="hidden w-10 px-1 py-2 text-center sm:table-cell" title="Empatados">E</th>
                <th class="hidden w-10 px-1 py-2 text-center sm:table-cell" title="Perdidos">P</th>
                <th class="hidden w-20 px-1 py-2 text-center sm:table-cell" title="Goles a favor y en contra">GF:GC</th>
                <th class="w-12 px-1 py-2 text-center sm:w-14" title="Diferencia de goles">DG</th>
                @if ($form->isNotEmpty())
                    <th class="hidden w-36 px-2 py-2 text-center lg:table-cell">RACHA</th>
                @endif
                <th class="w-16 bg-ink/60 px-2 py-2 text-center text-brand sm:w-20">PTS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $row)
                @php
                    $position = $index + 1;
                    $zone = $zones->first(fn ($candidate) => $candidate->covers($position));
                    $results = $form[$row->team->getKey()] ?? [];
                @endphp
                <tr class="border-t border-white/[0.055] transition-colors hover:bg-white/[0.04]">
                    <td class="border-l-4 py-2.5 pl-3 sm:pl-4 {{ $zone ? '' : 'border-transparent' }}"
                        @if ($zone) style="border-color: {{ $zone->hex() }}" @endif>
                        {{-- El color en línea sale de StandingZone::COLORS, una
                             paleta cerrada: ningún valor arbitrario llega a la página. --}}
                        <span class="grid h-6 w-7 -skew-x-12 place-items-center rounded-[3px] font-display text-sm font-bold {{ $zone ? 'text-ink' : 'text-white/55' }}"
                              @if ($zone) style="background-color: {{ $zone->hex() }}" title="{{ $zone->label }}" @endif>
                            <span class="skew-x-12">{{ $position }}</span>
                        </span>
                    </td>
                    <td class="px-2 py-2.5">
                        <a href="{{ route('site.clubs.show', ['club' => $row->team->club_id, 'temporada' => $row->team->season_id]) }}"
                           class="flex min-w-0 items-center gap-2.5 hover:text-brand">
                            <x-site.team-crest :team="$row->team" size="size-6" />
                            <span @class([
                                'truncate font-display text-base text-white sm:text-lg',
                                'font-extrabold uppercase italic' => $position === 1,
                                'font-semibold' => $position !== 1,
                            ])>{{ $row->team->name }}</span>
                        </a>
                    </td>
                    <td class="px-1 py-2.5 text-center tabular-nums text-white/75">{{ $row->played }}</td>
                    <td class="hidden px-1 py-2.5 text-center tabular-nums text-white/75 sm:table-cell">{{ $row->won }}</td>
                    <td class="hidden px-1 py-2.5 text-center tabular-nums text-white/75 sm:table-cell">{{ $row->drawn }}</td>
                    <td class="hidden px-1 py-2.5 text-center tabular-nums text-white/75 sm:table-cell">{{ $row->lost }}</td>
                    <td class="hidden px-1 py-2.5 text-center tabular-nums text-white/75 sm:table-cell">{{ $row->goals_for }}:{{ $row->goals_against }}</td>
                    <td class="px-1 py-2.5 text-center tabular-nums text-white/75">{{ $row->goal_difference > 0 ? '+' : '' }}{{ $row->goal_difference }}</td>
                    @if ($form->isNotEmpty())
                        <td class="hidden px-2 py-2.5 lg:table-cell">
                            {{-- Los colores de estado del sitio, SIEMPRE con su letra
                                 (ver home-form). --}}
                            <span class="flex justify-center gap-1" aria-label="Últimos partidos: {{ implode(', ', $results) }}">
                                @foreach ($results as $result)
                                    <span class="grid size-[18px] -skew-x-12 place-items-center rounded-[2px] font-mono text-[9px] font-bold text-white {{ $tones[$result] }}"
                                          title="{{ $names[$result] }}" aria-hidden="true"><span class="skew-x-12">{{ $result }}</span></span>
                                @endforeach
                            </span>
                        </td>
                    @endif
                    <td class="bg-ink/60 px-2 py-2.5 text-center">
                        <span class="inline-block -skew-x-8 font-display text-2xl font-extrabold leading-none tabular-nums text-brand">{{ $row->points }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($zones->isNotEmpty())
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-white/[0.055] px-4 py-3 font-mono text-[10px] tracking-[0.06em] text-white/55">
            @foreach ($zones as $zone)
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3.5 -skew-x-12 rounded-[2px]" style="background-color: {{ $zone->hex() }}"></span>
                    {{ mb_strtoupper($zone->label) }}
                </span>
            @endforeach
        </div>
    @endif
</section>
