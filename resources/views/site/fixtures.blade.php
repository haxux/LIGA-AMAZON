<x-layouts.site :title="'Partidos — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Partidos</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">FIXTURES</div>

    <x-site.filter-bar :action="route('site.fixtures')">
        <x-site.filter-select
            name="temporada"
            label="TEMPORADA"
            :options="$seasons->pluck('name', 'id')"
            :selected="$selectedSeason->id" />

        <x-site.filter-select
            name="division"
            label="DIVISIÓN"
            :options="collect([$all => 'Todas'])->union($divisions->pluck('name', 'id'))"
            :selected="$selectedDivision?->id ?? $all" />

        <x-site.filter-select
            name="jornada"
            label="JORNADA"
            :options="collect([$all => 'Todas'])->union($numbers->mapWithKeys(fn (int $number) => [$number => 'Jornada '.$number]))"
            :selected="$selectedNumber ?? $all" />
    </x-site.filter-bar>

    @forelse ($groups as $group)
        <section class="mb-10">
            <h2 class="font-display mb-4 rounded-[4px] bg-surface-alt px-4 py-2 text-lg uppercase tracking-wide text-brand">
                {{ $group['division']->name }}
            </h2>

            @foreach ($group['matchdays'] as $matchday)
                <div class="mb-6">
                    <h3 class="mb-3 flex flex-wrap items-baseline gap-2 font-mono text-xs uppercase tracking-[0.1em] text-ink/70">
                        <span>Jornada {{ $matchday->number }}</span>
                        @if ($matchday->date)
                            <span class="text-ink/45">· {{ $matchday->date->format('d/m/Y') }}</span>
                        @endif
                    </h3>

                    @if ($matchday->games->isEmpty())
                        <p class="font-mono text-[11px] tracking-[0.08em] text-ink/50">Sin partidos programados.</p>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($matchday->games as $game)
                                <x-site.game-card :game="$game" :matchday-number="$matchday->number" />
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    @empty
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            No hay partidos para este filtro.
        </p>
    @endforelse
</x-layouts.site>
