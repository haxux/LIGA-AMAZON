<x-layouts.site :title="'Clasificación — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Clasificación</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">LEAGUE TABLE</div>

    @foreach ($tables as $table)
        <x-site.standings-table :heading="$table['heading']" :rows="$table['rows']" />
    @endforeach
</x-layouts.site>
