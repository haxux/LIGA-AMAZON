<x-layouts.site :title="'Estadísticas — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Estadísticas</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">
        {{ mb_strtoupper($competition->isAll() ? 'Todas las competiciones' : $competition->label) }}
    </div>

    {{-- Sin copas en la temporada sólo quedarían «Todo» y «Liga», que es la
         misma lista dos veces: entonces no hay nada que elegir. --}}
    @if (count($competitionOptions) > 2)
        <x-site.filter-bar :action="route('site.scorers')">
            <x-site.filter-select name="competicion" label="COMPETICIÓN"
                                  :options="$competitionOptions" :selected="$competition->key" />
        </x-site.filter-bar>
    @endif

    <div class="grid gap-6 md:grid-cols-3">
        <x-site.scorer-list title="Máximos goleadores" :rows="$scorers" />
        <x-site.scorer-list title="Máximos asistentes" :rows="$assisters" />
        <x-site.scorer-list title="Máximas porterías a cero" :rows="$cleanSheets" />
    </div>
</x-layouts.site>
