@php($byMatchday = $games->groupBy(fn ($game) => $game->matchday?->number))

@forelse ($byMatchday as $number => $matchdayGames)
    <div class="mb-6">
        <h3 class="mb-3 font-mono text-xs uppercase tracking-[0.1em] text-ink/70">
            {{ $number === null ? 'Sin jornada' : 'Jornada '.$number }}
        </h3>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($matchdayGames as $game)
                <x-site.game-card :game="$game" :matchday-number="$number" />
            @endforeach
        </div>
    </div>
@empty
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        Este club todavía no tiene calendario en esta temporada.
    </p>
@endforelse
