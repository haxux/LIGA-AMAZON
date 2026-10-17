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

    {{-- Una división a la vez, como las copas: el mismo menú de la cabecera, a
         mano también aquí para el móvil. --}}
    @if ($divisions->count() > 1)
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Divisiones">
            @php($pill = 'rounded-full border px-4 py-1.5 font-display text-sm font-semibold uppercase tracking-[0.08em]')

            <a href="{{ route('site.clubs.index', ['temporada' => $selectedSeason->id]) }}"
               class="{{ $pill }} {{ $selectedDivision === null ? 'border-ink bg-ink text-white' : 'border-ink/30 text-ink/70 hover:border-ink hover:text-ink' }}">
                Todas
            </a>

            @foreach ($divisions as $division)
                <a href="{{ route('site.clubs.index', ['temporada' => $selectedSeason->id, 'division' => $division['id']]) }}"
                   class="{{ $pill }} {{ $selectedDivision === $division['id'] ? 'border-ink bg-ink text-white' : 'border-ink/30 text-ink/70 hover:border-ink hover:text-ink' }}">
                    {{ $division['name'] }}
                </a>
            @endforeach
        </nav>
    @endif

    @forelse ($groups as $group)
        <section class="mb-10">
            <h2 class="font-display mb-4 rounded-[4px] bg-surface-alt px-4 py-2 text-lg uppercase tracking-wide text-brand">
                {{ $group['heading'] }}
            </h2>

            {{-- Con una división elegida los equipos se llevan toda la página:
                 tarjetas más grandes, con el escudo a otra escala. --}}
            @php($single = $selectedDivision !== null)

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 {{ $single ? 'sm:gap-4' : '' }}">
                @foreach ($group['teams'] as $team)
                    <a href="{{ route('site.clubs.show', ['club' => $team->club_id, 'temporada' => $selectedSeason->id]) }}"
                       class="flex items-center gap-3 rounded-[5px] border-l-[3px] border-brand bg-surface-alt hover:bg-surface-muted {{ $single ? 'gap-4 p-6' : 'p-4' }}">
                        <x-site.club-crest :club="$team->club" :size="$single ? 'size-[56px]' : 'size-[34px]'" />

                        <span class="min-w-0 truncate font-display font-semibold text-white {{ $single ? 'text-2xl' : 'text-lg' }}">{{ $team->name }}</span>
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
