<x-layouts.site :title="'Goleadores — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Goleadores</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">TOP SCORERS</div>

    <div class="grid gap-6 md:grid-cols-2">
        <x-site.scorer-list title="Máximos goleadores" :rows="$scorers" />
        <x-site.scorer-list title="Máximos asistentes" :rows="$assisters" />
    </div>
</x-layouts.site>
