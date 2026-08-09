<x-layouts.site :title="'Partidos — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Partidos</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">FIXTURES</div>

    @foreach ($matchdays as $matchday)
        <section class="mb-8">
            <h2 class="mb-3 font-mono text-xs uppercase tracking-[0.1em] text-ink/70">Jornada {{ $matchday->number }}</h2>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($matchday->games as $game)
                    <x-site.game-card :game="$game" :matchday-number="$matchday->number" />
                @endforeach
            </div>
        </section>
    @endforeach
</x-layouts.site>
