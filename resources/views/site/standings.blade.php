<x-layouts.site :title="'Clasificación — Liga Amazon'">
    <h1 class="font-display mb-6 text-3xl uppercase tracking-wide">Clasificación</h1>

    @foreach ($tables as $table)
        <x-site.standings-table :heading="$table['heading']" :rows="$table['rows']" />
    @endforeach
</x-layouts.site>
