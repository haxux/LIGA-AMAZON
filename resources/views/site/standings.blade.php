<x-layouts.site :title="'Clasificación — Liga Amazon'">
    {{-- La temporada va en el subtítulo y no en un desplegable: al quitar el
         filtro, era lo único que le decía al visitante qué año está mirando. --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Clasificación</h1>
            <div class="font-mono text-[10px] tracking-[0.14em] text-ink/50">{{ mb_strtoupper($season->name) }}</div>
        </div>

        @if ($hasHistory)
            <a href="{{ route('site.history') }}"
               class="inline-flex shrink-0 items-center gap-2 rounded-[4px] bg-ink px-4 py-2 font-display text-sm font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-surface">
                Historia
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" />
                </svg>
            </a>
        @endif
    </div>

    @foreach ($tables as $table)
        <x-site.standings-table :heading="$table['heading']" :rows="$table['rows']" :zones="$table['zones']" />
    @endforeach
</x-layouts.site>
