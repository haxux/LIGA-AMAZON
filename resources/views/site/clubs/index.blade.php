<x-layouts.site :title="'Equipos — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Equipos</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">CLUBES</div>

    <x-site.filter-bar :action="route('site.clubs.index')">
        <x-site.filter-select
            name="temporada"
            label="TEMPORADA"
            :options="$seasons->pluck('name', 'id')"
            :selected="$selectedSeason->id" />
    </x-site.filter-bar>

    @forelse ($groups as $group)
        <section class="mb-10">
            <h2 class="font-display mb-4 rounded-[4px] bg-surface-alt px-4 py-2 text-lg uppercase tracking-wide text-brand">
                {{ $group['heading'] }}
            </h2>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($group['teams'] as $team)
                    <a href="{{ route('site.clubs.show', ['club' => $team->club_id, 'temporada' => $selectedSeason->id]) }}"
                       class="flex items-center gap-3 rounded-[5px] border-l-[3px] border-brand bg-surface-alt p-4 hover:bg-surface-muted">
                        <x-site.club-crest :club="$team->club" size="size-[34px]" />

                        <span class="min-w-0">
                            <span class="block truncate font-display text-lg font-semibold text-white">{{ $team->name }}</span>
                            <span class="block font-mono text-[10px] tracking-[0.12em] text-white/45">
                                {{ $team->short_name }}@if ($team->founded_year) · {{ $team->founded_year }}@endif
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            Todavía no hay equipos inscritos en esta temporada.
        </p>
    @endforelse
</x-layouts.site>
