{{-- Separadas por competición (Fase 15, decisión del propietario): primero la
     temporada entera y después cada competición que el club disputa. Todas a la
     vez y no con un selector, porque lo que se quiere ver de un vistazo es
     cuánto de lo hecho es de liga y cuánto de copa.

     Por eso van lado a lado, en bloques compactos con las cifras siempre en el
     mismo sitio: así se comparan mirando de uno a otro, y no bajando. --}}
<div @class([
        'grid items-start gap-4',
        'md:grid-cols-2' => $statBlocks->count() === 2,
        'md:grid-cols-2 xl:grid-cols-3' => $statBlocks->count() > 2,
     ])>
    @foreach ($statBlocks as $block)
        @php
            $stats = $block['stats'];
            $played = max(1, $stats->played);

            // Ganados, empatados y perdidos, con los colores de estado del
            // sitio y siempre con su nombre al lado (ver home-form).
            $outcomes = [
                ['Ganados', $stats->won, 'bg-win'],
                ['Empatados', $stats->drawn, 'bg-draw'],
                ['Perdidos', $stats->lost, 'bg-loss'],
            ];

            // Tarjetas y porterías a cero salen de `game_events`, que las registra
            // desde 2026-09-20; el resto, de los marcadores (design D12).
            $discipline = [
                'AMARILLAS' => [$stats->yellow_cards, 'bg-[#F5C518]'],
                'ROJAS' => [$stats->red_cards, 'bg-[#D6343F]'],
                'PORTERÍAS A CERO' => [$stats->clean_sheets, null],
                'MVP DEL PARTIDO' => [$stats->mvps, null],
            ];

            // El Week MVP es de la jornada, que sólo existe en la liga: en una
            // copa no se enseña.
            if ($block['competition']->isAll() || $block['competition']->isLeague()) {
                $discipline['WEEK MVP'] = [$stats->week_mvps, null];
            }
        @endphp

        <section class="rounded-md bg-surface p-4">
            {{-- Sin copas no hay nada que separar, y entonces tampoco hay rótulo
                 que poner. --}}
            @if ($block['title'])
                <x-site.panel-title>{{ mb_strtoupper($block['title']) }}</x-site.panel-title>
            @endif

            {{-- Las cifras de cabecera. Los puntos sólo donde hay tabla: en una
                 copa a eliminatoria no significan nada, y en «General» tampoco,
                 porque sumaría partidos que no dan puntos. --}}
            <dl class="grid grid-cols-3 gap-2">
                <div class="rounded-[5px] bg-surface-alt px-3 py-2.5">
                    <dt class="font-mono text-[9px] tracking-[0.12em] text-white/60" title="Partidos jugados">PJ</dt>
                    <dd class="-skew-x-8 font-display text-3xl font-extrabold leading-none tabular-nums text-white">{{ $stats->played }}</dd>
                </div>
                <div class="rounded-[5px] bg-surface-alt px-3 py-2.5">
                    <dt class="font-mono text-[9px] tracking-[0.12em] text-white/60" title="Diferencia de goles">DG</dt>
                    <dd class="-skew-x-8 font-display text-3xl font-extrabold leading-none tabular-nums text-white">{{ ($stats->goal_difference > 0 ? '+' : '').$stats->goal_difference }}</dd>
                </div>
                @if ($block['competition']->isLeague())
                    <div class="rounded-[5px] bg-surface-alt px-3 py-2.5">
                        <dt class="font-mono text-[9px] tracking-[0.12em] text-brand" title="Puntos">PTS</dt>
                        <dd class="-skew-x-8 font-display text-3xl font-extrabold leading-none tabular-nums text-brand">{{ $stats->points }}</dd>
                    </div>
                @endif
            </dl>

            {{-- Cómo acabaron los partidos: una barra partida en tres, con la
                 cuenta de cada parte debajo. --}}
            <div class="mt-4">
                <div class="flex h-3 gap-0.5 overflow-hidden rounded-[2px] bg-white/10" aria-hidden="true">
                    @foreach ($outcomes as [$name, $count, $tone])
                        @if ($count > 0)
                            <span class="{{ $tone }}" style="width: {{ round($count / $played * 100, 1) }}%"></span>
                        @endif
                    @endforeach
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($outcomes as [$name, $count, $tone])
                        <span class="flex items-center gap-1.5 font-display text-sm text-white/70">
                            <span class="size-2.5 -skew-x-12 rounded-[1px] {{ $tone }}" aria-hidden="true"></span>
                            <span class="font-bold tabular-nums text-white">{{ $count }}</span> {{ $name }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Goles a favor contra goles en contra, enfrentados. --}}
            <div class="mt-4 flex items-end justify-between rounded-[5px] bg-surface-alt px-4 py-3">
                <div>
                    <div class="font-mono text-[9px] tracking-[0.12em] text-white/60">GOLES A FAVOR</div>
                    <div class="-skew-x-8 font-display text-4xl font-extrabold leading-none tabular-nums text-white">{{ $stats->goals_for }}</div>
                </div>
                <span class="pb-1 font-display text-2xl font-bold text-white/25" aria-hidden="true">:</span>
                <div class="text-right">
                    <div class="font-mono text-[9px] tracking-[0.12em] text-white/60">GOLES EN CONTRA</div>
                    <div class="-skew-x-8 font-display text-4xl font-extrabold leading-none tabular-nums text-white/70">{{ $stats->goals_against }}</div>
                </div>
            </div>

            {{-- Disciplina y premios, en fichas pequeñas. Las tarjetas llevan su
                 color: ahí el color ES el dato. --}}
            <dl class="mt-4 grid grid-cols-2 gap-2">
                @foreach ($discipline as $label => [$value, $card])
                    <div class="rounded-[5px] bg-surface-alt px-3 py-2">
                        <dt class="flex items-center gap-1.5 truncate font-mono text-[9px] tracking-[0.1em] text-white/60">
                            @if ($card) <span class="h-3 w-2 shrink-0 rounded-[1px] {{ $card }}" aria-hidden="true"></span> @endif
                            {{ $label }}
                        </dt>
                        <dd class="font-display text-xl font-bold tabular-nums text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
