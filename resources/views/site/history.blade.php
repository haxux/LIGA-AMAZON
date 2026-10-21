<x-layouts.site :title="'Historia — Liga Amazon'">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Historia</h1>
            <div class="font-mono text-[10px] tracking-[0.14em] text-ink/70">
                {{ $selectedSeason ? mb_strtoupper($selectedSeason->name) : 'TEMPORADAS CERRADAS' }}
            </div>
        </div>

        <a href="{{ route('site.standings') }}"
           class="inline-flex shrink-0 items-center gap-2 rounded-[4px] bg-ink px-4 py-2 font-display text-sm font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-surface">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true">
                <path d="M15 6l-6 6 6 6" />
            </svg>
            Clasificación
        </a>
    </div>

    @if ($seasons->isEmpty())
        {{-- Honesto y no en blanco: las temporadas sin un solo partido jugado no
             entran en el archivo, así que mientras sólo exista la vigente aquí
             no hay nada que enseñar. Se dice, en vez de ofrecer un desplegable
             de temporadas que al elegirlas salen a ceros. --}}
        <section class="rounded-md bg-surface p-6">
            <p class="font-display text-lg text-white">Todavía no hay temporadas cerradas.</p>
            <p class="mt-2 max-w-prose text-sm text-white/60">
                Cuando termine la temporada en curso y empiece la siguiente, su clasificación
                final aparecerá aquí junto a las de todas las anteriores.
            </p>
        </section>
    @else
        <x-site.filter-bar :action="route('site.history')">
            <x-site.filter-select
                name="temporada"
                label="TEMPORADA"
                :options="$seasons->pluck('name', 'id')"
                :selected="$selectedSeason->id" />
        </x-site.filter-bar>

        <x-site.season-honours :champions="$champions" :scorers="$scorers" />

        @foreach ($tables as $table)
            <x-site.standings-table :heading="$table['heading']" :rows="$table['rows']" :zones="$table['zones']" :champion="$table['champion'] ?? null" />
        @endforeach
    @endif
</x-layouts.site>
