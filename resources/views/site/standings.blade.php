<x-layouts.site :title="'Clasificación — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Clasificación</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">LEAGUE TABLE</div>

    <x-site.filter-bar :action="route('site.standings')">
        <x-site.filter-select
            name="temporada"
            label="TEMPORADA"
            :options="$seasons->pluck('name', 'id')"
            :selected="$selectedSeason->id" />
    </x-site.filter-bar>

    @foreach ($tables as $table)
        <x-site.standings-table :heading="$table['heading']" :rows="$table['rows']" :zones="$table['zones']" />
    @endforeach
</x-layouts.site>
