{{-- General: lo que alguien quiere saber de un club de un vistazo — qué juega
     después, cómo viene, dónde está, quién marca, y cómo se alinea. --}}
<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-[6px] bg-surface-alt p-4">
        <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">PRÓXIMO PARTIDO</h2>

        @if ($nextGame)
            <x-site.game-card :game="$nextGame" :matchday-number="$nextGame->matchday?->number" />
            @if ($nextGame->kickoff_at)
                <p class="mt-2 font-mono text-[10px] tracking-[0.1em] text-white/45">
                    {{ $nextGame->kickoff_at->format('d/m/Y H:i') }}
                </p>
            @endif
        @else
            <p class="font-mono text-[11px] tracking-[0.08em] text-white/50">No queda ninguno por jugar.</p>
        @endif
    </section>

    <section class="rounded-[6px] bg-surface-alt p-4">
        <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">ÚLTIMOS RESULTADOS</h2>

        @forelse ($recentResults as $result)
            <div class="mb-2 flex items-center gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-[3px] font-display text-sm font-bold text-white
                    {{ ['G' => 'bg-emerald-700', 'E' => 'bg-white/20', 'P' => 'bg-rose-900'][$result['outcome']] }}">
                    {{ $result['outcome'] }}
                </span>
                <span class="min-w-0 flex-1 truncate font-display text-base text-white/80">{{ $result['opponent']?->name }}</span>
                <span class="font-mono text-xs text-white/60">{{ $result['scored'] }}–{{ $result['conceded'] }}</span>
            </div>
        @empty
            <p class="font-mono text-[11px] tracking-[0.08em] text-white/50">Todavía sin partidos jugados.</p>
        @endforelse
    </section>

    <section class="rounded-[6px] bg-surface-alt p-4">
        <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">EN LA TABLA</h2>

        <p class="font-display text-5xl font-bold text-brand">{{ $position ?? '—' }}</p>
        <p class="mt-1 font-mono text-[10px] tracking-[0.1em] text-white/45">
            {{ $team->division?->name ? mb_strtoupper($team->division->name) : 'SIN DIVISIÓN' }}
        </p>

        <div class="mt-4 space-y-2 border-t border-white/10 pt-3">
            <div>
                <span class="block font-mono text-[9px] tracking-[0.12em] text-white/40">MÁXIMO GOLEADOR</span>
                <span class="font-display text-base text-white">
                    {{ $topScorer?->player->name ?? '—' }}
                    @if ($topScorer) <span class="text-brand">{{ $topScorer->count }}</span> @endif
                </span>
            </div>
            <div>
                <span class="block font-mono text-[9px] tracking-[0.12em] text-white/40">MÁXIMO ASISTENTE</span>
                <span class="font-display text-base text-white">
                    {{ $topAssister?->player->name ?? '—' }}
                    @if ($topAssister) <span class="text-brand">{{ $topAssister->count }}</span> @endif
                </span>
            </div>
        </div>
    </section>
</div>

<section class="mt-4 rounded-[6px] bg-surface-alt p-4">
    <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">ONCE IDEAL</h2>

    @if ($canEditLineup)
        {{-- Sólo el técnico de este club y sólo en la temporada vigente recibe
             el componente; a cualquier otro visitante se le sirve el campo
             dibujado, sin JavaScript de más. El componente vuelve a comprobar
             el permiso en cada acción: esto es la cortesía, no la cerradura. --}}
        <livewire:starting-eleven-editor :team="$team" />
    @elseif ($lineup)
        <x-site.pitch :lineup="$lineup" :team="$team" />
    @else
        <p class="font-mono text-[11px] tracking-[0.08em] text-white/50">
            El técnico todavía no ha armado el once de esta temporada.
        </p>
    @endif
</section>
