<x-layouts.site :title="'Partidos — Liga Amazon'">
    <h1 class="font-display mb-6 text-3xl uppercase tracking-wide">Partidos</h1>

    @foreach ($matchdays as $matchday)
        <section class="mb-8">
            <h2 class="font-display mb-3 text-lg uppercase tracking-wide text-brand">Jornada {{ $matchday->number }}</h2>

            <div class="space-y-2">
                @foreach ($matchday->games as $game)
                    <x-site.game-card :game="$game" />
                @endforeach
            </div>
        </section>
    @endforeach
</x-layouts.site>
