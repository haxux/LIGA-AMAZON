<x-layouts.site :title="'Clasificación — Liga Amazon'">
    {{-- La temporada va en el subtítulo y no en un desplegable: al quitar el
         filtro, era lo único que le decía al visitante qué año está mirando. --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display mb-1 -skew-x-6 text-4xl font-extrabold uppercase italic tracking-wide text-ink sm:text-5xl">Clasificación</h1>
            <div class="font-mono text-[10px] tracking-[0.14em] text-ink/50">{{ mb_strtoupper($season->name) }}</div>
        </div>

        @if ($hasHistory)
            <a href="{{ route('site.history') }}"
               class="inline-flex shrink-0 -skew-x-12 items-center gap-2 rounded-[3px] bg-ink px-4 py-2 font-display text-sm font-bold uppercase italic tracking-[0.08em] text-brand transition-colors hover:bg-surface">
                <span class="skew-x-12">Historia</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" />
                </svg>
            </a>
        @endif
    </div>

    @foreach ($tables as $table)
        <x-site.standings-table :heading="$table['heading']" :rows="$table['rows']" :zones="$table['zones']" :form="$form" />
    @endforeach
</x-layouts.site>
