{{-- Un partido en la lista de Partidos, como una fila de marcador: el local a
     la derecha de su mitad, el visitante a la izquierda de la suya y el
     resultado en medio, en un bloque oscuro inclinado. El ganador va en
     blanco pleno y el perdedor apagado; sin jugar, en vez del resultado va la
     hora (o «VS»).

     Los lados que una anulación perjudica van en rojo, como en game-card.
     `label` es la ronda, para los partidos de copa, que no tienen jornada. --}}
@props(['game', 'label' => null])

@php
    $played = $game->home_score !== null && $game->away_score !== null;
    $penalized = $game->penalizedSides();
    $winner = ! $played ? null : ($game->home_score > $game->away_score ? 'home' : ($game->home_score < $game->away_score ? 'away' : null));
    $themeStyle = $game->cup()?->themeStyle() ?? '';

    $tone = fn (string $side) => match (true) {
        in_array($side, $penalized, true) => 'text-rose-500',
        ! $played, $winner === null, $winner === $side => 'text-white',
        default => 'text-white/55',
    };
@endphp

<a href="{{ route('site.games.show', $game) }}" style="{{ $themeStyle }}"
   class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-x-3 gap-y-2 rounded-md bg-surface px-3 py-3 transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-brand sm:px-4">
    @if ($label)
        <span class="col-span-3 truncate text-center font-mono text-[9px] uppercase tracking-[0.1em] text-white/45">{{ $label }}</span>
    @endif

    <span class="flex min-w-0 items-center justify-end gap-2.5 text-right">
        <span class="truncate font-display text-base font-bold sm:text-lg {{ $tone('home') }}"
              @if (in_array('home', $penalized, true)) title="Partido anulado en su contra" @endif>{{ $game->homeTeam->name }}</span>
        <x-site.team-crest :team="$game->homeTeam" size="size-7" />
    </span>

    <span class="grid min-w-[84px] -skew-x-8 place-items-center rounded-[4px] bg-ink px-3 py-1.5">
        @if ($played)
            <span class="font-display text-2xl font-extrabold leading-none tabular-nums text-brand">{{ $game->home_score }}<span class="px-1 text-white/30">–</span>{{ $game->away_score }}</span>
        @else
            <span class="font-display text-base font-bold leading-none tabular-nums text-white/60">{{ $game->kickoff_at ? $game->kickoff_at->format('H:i') : 'VS' }}</span>
        @endif
    </span>

    <span class="flex min-w-0 items-center gap-2.5">
        <x-site.team-crest :team="$game->awayTeam" size="size-7" />
        <span class="truncate font-display text-base font-bold sm:text-lg {{ $tone('away') }}"
              @if (in_array('away', $penalized, true)) title="Partido anulado en su contra" @endif>{{ $game->awayTeam->name }}</span>
    </span>
</a>
