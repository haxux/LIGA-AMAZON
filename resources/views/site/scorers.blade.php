<x-layouts.site :title="'Goleadores — Liga Amazon'">
    <h1 class="font-display mb-6 text-3xl uppercase tracking-wide">Goleadores</h1>

    <div class="grid gap-6 md:grid-cols-2">
        <x-site.scorer-list title="Máximos goleadores" :rows="$scorers" />
        <x-site.scorer-list title="Máximos asistentes" :rows="$assisters" />
    </div>
</x-layouts.site>
