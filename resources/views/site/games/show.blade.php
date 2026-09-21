@php
    $isPlayed = $game->home_score !== null && $game->away_score !== null;
    $matchday = $game->matchday;
    $stadium = $game->homeTeam?->club?->stadium;

    // El vocabulario de `GameEvent` está en inglés desde la Fase 2 y el sitio
    // público es español, así que la traducción vive aquí, como la de las
    // posiciones en la ficha de club.
    $labels = [
        \App\Models\GameEvent::TYPE_GOAL => 'Gol',
        \App\Models\GameEvent::TYPE_ASSIST => 'Asistencia',
        \App\Models\GameEvent::TYPE_YELLOW_CARD => 'Tarjeta amarilla',
        \App\Models\GameEvent::TYPE_RED_CARD => 'Tarjeta roja',
        \App\Models\GameEvent::TYPE_CLEAN_SHEET => 'Portería a cero',
    ];

    $colors = [
        \App\Models\GameEvent::TYPE_GOAL => 'text-brand',
        \App\Models\GameEvent::TYPE_ASSIST => 'text-white/70',
        \App\Models\GameEvent::TYPE_YELLOW_CARD => 'text-amber-400',
        \App\Models\GameEvent::TYPE_RED_CARD => 'text-rose-400',
        \App\Models\GameEvent::TYPE_CLEAN_SHEET => 'text-emerald-400',
    ];
@endphp

<x-layouts.site :title="$game->homeTeam?->name.' – '.$game->awayTeam?->name.' — Liga Amazon'">
    @php($cup = $game->cup())

    <div class="mb-4 font-mono text-[10px] tracking-[0.14em] text-ink/50">
        @if ($cup)
            <a href="{{ route('site.cups.show', $cup) }}" class="hover:text-ink">{{ mb_strtoupper($cup->name) }}</a>
            · {{ mb_strtoupper($game->competitionLabel()) }}
            @if ($cup->season) · {{ mb_strtoupper($cup->season->name) }} @endif
        @else
            <a href="{{ route('site.fixtures', ['temporada' => $matchday?->season_id]) }}" class="hover:text-ink">PARTIDOS</a>
            @if ($matchday?->division) · {{ mb_strtoupper($matchday->division->name) }} @endif
            @if ($matchday) · JORNADA {{ $matchday->number }} @endif
            @if ($matchday?->season) · {{ mb_strtoupper($matchday->season->name) }} @endif
        @endif
    </div>

    {{-- El marcador, que es lo que se viene a ver. --}}
    <section class="mb-6 rounded-[6px] border-t-[3px] border-brand bg-surface-alt p-6">
        <div class="flex items-center justify-between gap-4">
            @foreach ([['team' => $game->homeTeam, 'score' => $game->home_score], ['team' => $game->awayTeam, 'score' => $game->away_score]] as $index => $side)
                @if ($index === 1)
                    <span class="font-mono text-xs tracking-[0.14em] text-white/40">{{ $isPlayed ? '–' : 'VS' }}</span>
                @endif

                <a href="{{ route('site.clubs.show', ['club' => $side['team']?->club_id, 'temporada' => $matchday?->season_id]) }}"
                   class="flex min-w-0 flex-1 flex-col items-center gap-2 text-center hover:opacity-80">
                    <x-site.team-crest :team="$side['team']" size="size-[48px]" />
                    <span class="truncate font-display text-xl font-semibold text-white">{{ $side['team']?->name }}</span>
                    <span class="font-display text-4xl font-bold {{ $isPlayed ? 'text-brand' : 'text-white/30' }}">
                        {{ $isPlayed ? $side['score'] : '–' }}
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1 border-t border-white/10 pt-3 font-mono text-[10px] tracking-[0.12em] text-white/45">
            <span>{{ $isPlayed ? 'JUGADO' : 'POR JUGAR' }}</span>
            @if ($game->kickoff_at) <span>{{ $game->kickoff_at->format('d/m/Y H:i') }}</span>
            @elseif ($matchday?->date) <span>{{ $matchday->date->format('d/m/Y') }}</span> @endif
            @if ($stadium) <span>{{ mb_strtoupper($stadium->name) }}</span> @endif
        </div>
    </section>

    <section>
        <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">EVENTOS</h2>

        @forelse ($events as $entry)
            @php($event = $entry['event'])
            @php($isHome = $entry['side'] === 'home')

            {{-- El local a la izquierda y el visitante a la derecha, como en el
                 marcador: quién lo hizo se lee del lado, no de una etiqueta. --}}
            <div class="mb-2 flex items-center gap-3 rounded-[5px] bg-surface-alt px-4 py-2 {{ $isHome ? '' : 'flex-row-reverse text-right' }}">
                <span class="w-10 shrink-0 font-mono text-xs text-white/50 {{ $isHome ? '' : 'text-right' }}">
                    {{ $event->minute ? $event->minute."'" : '—' }}
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-base font-semibold text-white">
                        {{ $event->player?->name ?? 'Jugador retirado' }}
                    </span>
                    <span class="block font-mono text-[10px] tracking-[0.1em] {{ $colors[$event->type] ?? 'text-white/60' }}">
                        {{ $labels[$event->type] ?? $event->type }}
                    </span>
                </span>
            </div>
        @empty
            <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
                Sin eventos registrados{{ $isPlayed ? ': sólo consta el resultado.' : ' todavía.' }}
            </p>
        @endforelse
    </section>
</x-layouts.site>
