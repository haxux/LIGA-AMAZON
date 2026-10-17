@php
    $heading = match ($scope) {
        \App\Enums\CupScope::National => 'Copas nacionales',
        \App\Enums\CupScope::International => 'Copas internacionales',
        default => 'Copas',
    };

    // Sin `?tipo` salen las dos familias, cada una en su bloque.
    [$international, $national] = $cups->partition(fn ($cup) => $cup->theme() !== null);
@endphp

<x-layouts.site :title="$heading.' — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">{{ $heading }}</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">ELIMINATORIAS</div>

    @if ($seasons->isNotEmpty())
        <x-site.filter-bar :action="route('site.cups.index')">
            @if (request('tipo'))
                <input type="hidden" name="tipo" value="{{ request('tipo') }}">
            @endif

            <x-site.filter-select
                name="temporada"
                label="TEMPORADA"
                :options="$seasons->pluck('name', 'id')"
                :selected="$selectedSeason?->id" />
        </x-site.filter-bar>
    @endif

    {{-- Las internacionales, como carteles: cada una con su propio escenario,
         el mismo que la envuelve al entrar. --}}
    @if ($international->isNotEmpty())
        @if ($scope === null)
            <h2 class="font-display mb-3 text-lg uppercase tracking-wide text-ink/70">Internacionales</h2>
        @endif

        <div class="mb-10 grid gap-4 md:grid-cols-2">
            @foreach ($international as $cup)
                @php($logo = $cup->logoUrl())
                @php($background = $cup->backgroundUrl())

                {{-- Con banner subido, el banner arriba y el nombre en una franja
                     debajo (el banner ya trae su rótulo); sin él, el escenario de
                     la copa con su logo en el centro. --}}
                <a href="{{ route('site.cups.show', $cup) }}"
                   style="{{ $cup->themeStyle() }}"
                   class="cup-banner {{ $cup->theme()->stageClass() }} group flex flex-col overflow-hidden rounded-md border-b-4 border-brand shadow-lg transition hover:-translate-y-0.5 hover:shadow-2xl">
                    @if ($background)
                        <img src="{{ $background }}" loading="lazy" decoding="async" alt="" class="block aspect-[16/8] w-full object-cover transition group-hover:scale-[1.02]">
                    @else
                        <span class="flex aspect-[16/8] items-center justify-center">
                            @if ($logo)
                                <img src="{{ $logo }}" loading="lazy" decoding="async" alt="" class="h-28 w-auto object-contain drop-shadow-[0_6px_18px_rgba(0,0,0,.5)] transition group-hover:scale-105">
                            @else
                                <span class="font-display text-7xl text-brand drop-shadow" aria-hidden="true">&#9819;</span>
                            @endif
                        </span>
                    @endif

                    <span class="flex items-center gap-3 bg-surface-alt px-5 py-4">
                        @if ($logo && $background)
                            <img src="{{ $logo }}" loading="lazy" decoding="async" alt="" class="h-10 w-auto shrink-0 object-contain">
                        @endif
                        <span class="min-w-0">
                            <span class="block font-mono text-[9px] tracking-[0.3em] text-brand">{{ mb_strtoupper($cup->theme()->label()) }}</span>
                            <span class="block truncate font-display text-2xl font-bold uppercase tracking-wide text-white">{{ $cup->name }}</span>
                            <span class="block font-mono text-[10px] tracking-[0.12em] text-white/55">
                                {{ $cup->participants_count }} EQUIPOS
                                @if ($cup->has_group_stage) · CON FASE DE GRUPOS @else · ELIMINATORIA DIRECTA @endif
                            </span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($national->isNotEmpty())
        @if ($scope === null && $international->isNotEmpty())
            <h2 class="font-display mb-3 text-lg uppercase tracking-wide text-ink/70">Nacionales</h2>
        @endif

        @foreach ($national as $cup)
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
        @endforeach
    @endif

    @if ($cups->isEmpty())
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            @if ($scope === \App\Enums\CupScope::International)
                Todavía no hay copas internacionales en esta temporada.
            @elseif ($scope === \App\Enums\CupScope::National)
                Todavía no hay copas nacionales en esta temporada.
            @else
                Todavía no hay copas en esta temporada.
            @endif
        </p>
    @endif
</x-layouts.site>
