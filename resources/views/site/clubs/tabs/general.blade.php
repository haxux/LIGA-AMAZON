{{-- General: lo que alguien quiere saber de un club de un vistazo — qué juega
     después, cómo viene, quién marca y cómo se alinea. El puesto ya no va aquí:
     lo lleva el marcador de la cabecera, en todas las pestañas. --}}
@php
    // La racha con las mismas letras y colores que el resto del sitio (V/E/D,
    // ver home-form): el servicio la da en G/E/P.
    $letters = ['G' => 'V', 'E' => 'E', 'P' => 'D'];
    $tones = ['V' => 'bg-win', 'E' => 'bg-draw', 'D' => 'bg-loss'];
    $names = ['V' => 'Victoria', 'E' => 'Empate', 'D' => 'Derrota'];
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-md bg-surface p-4">
        <x-site.panel-title>PRÓXIMO PARTIDO</x-site.panel-title>

        @if ($nextGame)
            <x-site.game-card :game="$nextGame" :matchday-number="$nextGame->matchday?->number" />
            @if ($nextGame->kickoff_at)
                <p class="mt-2 font-mono text-[10px] tracking-[0.1em] text-white/60">
                    {{ $nextGame->kickoff_at->format('d/m/Y H:i') }}
                </p>
            @endif
        @else
            <p class="text-sm text-white/55">No queda ninguno por jugar.</p>
        @endif
    </section>

    <section class="rounded-md bg-surface p-4">
        <x-site.panel-title>ÚLTIMOS RESULTADOS</x-site.panel-title>

        @forelse ($recentResults as $result)
            @php($letter = $letters[$result['outcome']])
            <a href="{{ route('site.games.show', $result['game']) }}"
               class="-mx-2 flex items-center gap-3 rounded-[4px] px-2 py-1.5 transition-colors hover:bg-surface-alt">
                <span class="grid size-6 shrink-0 -skew-x-12 place-items-center rounded-[3px] font-display text-sm font-bold text-white {{ $tones[$letter] }}"
                      title="{{ $names[$letter] }}"><span class="skew-x-12">{{ $letter }}</span></span>
                <x-site.team-crest :team="$result['opponent']" size="size-5" />
                <span class="min-w-0 flex-1 truncate font-display text-base font-semibold text-white/85">{{ $result['opponent']?->name }}</span>
                <span class="-skew-x-8 font-display text-lg font-bold tabular-nums text-white">{{ $result['scored'] }}–{{ $result['conceded'] }}</span>
            </a>
        @empty
            <p class="text-sm text-white/55">Todavía sin partidos jugados.</p>
        @endforelse
    </section>

    {{-- Las figuras del club: quien más marca y quien más asiste, con la cifra
         en grande como el líder de cada tabla de Estadísticas. --}}
    <section class="rounded-md bg-surface p-4">
        <x-site.panel-title>FIGURAS</x-site.panel-title>

        <div class="grid gap-2">
            @foreach ([['MÁXIMO GOLEADOR', $topScorer], ['MÁXIMO ASISTENTE', $topAssister]] as [$label, $leader])
                <div class="rounded-[5px] bg-surface-alt px-3 py-3">
                    <div class="mb-2 font-mono text-[9px] tracking-[0.12em] text-white/60">{{ $label }}</div>

                    @if ($leader)
                        <a href="{{ route('site.players.show', array_filter(['player' => $leader->player, 'temporada' => $selectedSeason?->id])) }}"
                           class="flex items-center gap-3">
                            <x-site.player-avatar :player="$leader->player" size="size-10" initials class="text-sm ring-2 ring-brand" />
                            <span class="min-w-0 flex-1 truncate font-display text-lg font-bold uppercase italic leading-tight text-white">{{ $leader->player->name }}</span>
                            <span class="-skew-x-8 font-display text-4xl font-extrabold leading-none tabular-nums text-brand">{{ $leader->count }}</span>
                        </a>
                    @else
                        <p class="text-sm text-white/55">Nadie todavía.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</div>

<section class="mt-4 rounded-md bg-surface p-4">
    <x-site.panel-title>ONCE IDEAL</x-site.panel-title>

    @if ($canEditLineup)
        {{-- Sólo el técnico de este club y sólo en la temporada vigente recibe
             el componente; a cualquier otro visitante se le sirve el campo
             dibujado, sin JavaScript de más. El componente vuelve a comprobar
             el permiso en cada acción: esto es la cortesía, no la cerradura. --}}
        <livewire:starting-eleven-editor :team="$team" />
    @elseif ($lineup)
        <x-site.pitch :lineup="$lineup" :team="$team" />
    @else
        <p class="text-sm text-white/55">
            El técnico todavía no ha armado el once de esta temporada.
        </p>
    @endif
</section>
