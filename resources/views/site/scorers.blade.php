<x-layouts.site :title="'Estadísticas — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Estadísticas</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">
        {{ mb_strtoupper($competition->isAll() ? 'Todas las competiciones' : $competition->label) }}
    </div>

    {{-- La competición sólo se ofrece si hay copas (sin ellas «Todo» y «Liga»
         son la misma lista). División y fecha son de la liga: al elegirlas
         las copas quedan fuera de las cifras. --}}
    <x-site.filter-bar :action="route('site.scorers')">
        @if (count($competitionOptions) > 2)
            <x-site.filter-select name="competicion" label="COMPETICIÓN"
                                  :options="$competitionOptions" :selected="$competition->key" />
        @endif
        <x-site.filter-select name="division" label="DIVISIÓN"
                              :options="$divisionOptions" :selected="$selectedDivision" />
        <x-site.filter-select name="fecha" label="FECHA"
                              :options="$fechaOptions" :selected="$selectedFecha" />
    </x-site.filter-bar>

    <div class="grid gap-6 md:grid-cols-3">
        <x-site.scorer-list title="Máximos goleadores" :rows="$scorers" />
        <x-site.scorer-list title="Máximos asistentes" :rows="$assisters" />
        <x-site.scorer-list title="Máximas porterías a cero" :rows="$cleanSheets" />
    </div>
</x-layouts.site>
