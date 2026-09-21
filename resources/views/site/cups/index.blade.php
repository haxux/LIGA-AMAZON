<x-layouts.site :title="'Copas — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Copas</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">ELIMINATORIAS</div>

    @if ($seasons->isNotEmpty())
        <x-site.filter-bar :action="route('site.cups.index')">
            <x-site.filter-select
                name="temporada"
                label="TEMPORADA"
                :options="$seasons->pluck('name', 'id')"
                :selected="$selectedSeason?->id" />
        </x-site.filter-bar>
    @endif

    @forelse ($cups as $cup)
        <a href="{{ route('site.cups.show', $cup) }}"
           class="mb-3 flex items-center gap-4 rounded-[5px] border-l-[3px] border-brand bg-surface-alt p-4 hover:bg-surface-muted">
            <span class="font-display text-2xl text-brand" aria-hidden="true">&#9819;</span>

            <span class="min-w-0">
                <span class="block truncate font-display text-lg font-semibold text-white">{{ $cup->name }}</span>
                <span class="block font-mono text-[10px] tracking-[0.12em] text-white/45">
                    {{ $cup->participants_count }} EQUIPOS
                    @if ($cup->has_group_stage) · CON FASE DE GRUPOS @else · ELIMINATORIA DIRECTA @endif
                </span>
            </span>
        </a>
    @empty
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            Todavía no hay copas en esta temporada.
        </p>
    @endforelse
</x-layouts.site>
