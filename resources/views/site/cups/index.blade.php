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
    <h1 class="font-display mb-1 -skew-x-6 text-4xl font-extrabold uppercase italic tracking-wide text-ink sm:text-5xl">{{ $heading }}</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/70">{{ $selectedSeason ? mb_strtoupper($selectedSeason->name) : 'ELIMINATORIAS' }}</div>

    {{-- El filtro de temporada, como en Estadísticas: con el tipo de copa a
         la vista al lado, en botones, para pasar de unas a otras. --}}
    @if ($seasons->isNotEmpty())
        <div class="mb-8 grid gap-3 rounded-md border-t-4 border-brand bg-surface p-4">
            <form method="GET" action="{{ route('site.cups.index') }}"
                  class="grid gap-1.5 sm:grid-cols-[7.5rem_minmax(0,1fr)] sm:items-center sm:gap-4">
                <label for="filtro-temporada" class="font-mono text-[10px] tracking-[0.12em] text-white/60">TEMPORADA</label>
                <div class="flex gap-2">
                    <select id="filtro-temporada" name="temporada" onchange="this.form.submit()"
                            class="w-full rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white focus-visible:outline-2 focus-visible:outline-brand sm:max-w-[260px]">
                        @foreach ($seasons->pluck('name', 'id') as $value => $text)
                            <option value="{{ $value }}" @selected((string) $value === (string) $selectedSeason?->id)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @if (request('tipo'))
                        <input type="hidden" name="tipo" value="{{ request('tipo') }}">
                    @endif
                    <noscript>
                        <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
                    </noscript>
                </div>
            </form>

            <x-site.stat-segments label="TIPO"
                                  :options="['' => 'Todas', 'nacionales' => 'Nacionales', 'internacionales' => 'Internacionales']"
                                  :selected="(string) request('tipo')"
                                  :url="fn ($value) => route('site.cups.index', array_filter(['temporada' => $selectedSeason?->id, 'tipo' => $value]))" />
        </div>
    @endif

    {{-- Las internacionales, como carteles: cada una con su propio escenario,
         el mismo que la envuelve al entrar. --}}
    @if ($international->isNotEmpty())
        @if ($scope === null)
            <h2 class="font-display mb-4 -skew-x-6 text-3xl font-extrabold uppercase italic tracking-wide text-ink">Internacionales</h2>
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
                            <span class="block truncate font-display text-2xl font-extrabold uppercase italic tracking-wide text-white">{{ $cup->name }}</span>
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
            <h2 class="font-display mb-4 -skew-x-6 text-3xl font-extrabold uppercase italic tracking-wide text-ink">Nacionales</h2>
        @endif

        {{-- Las nacionales, como tarjetas del sitio: la placa de trofeo, el
             nombre en cursiva y su formato en chapas. --}}
        <div class="mb-10 grid gap-4 md:grid-cols-2">
            @foreach ($national as $cup)
                <a href="{{ route('site.cups.show', $cup) }}"
                   class="flex items-center gap-5 rounded-md bg-surface p-5 transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-brand">
                    <span class="grid size-16 shrink-0 -skew-x-12 place-items-center rounded-[5px] bg-brand text-ink" aria-hidden="true">
                        <svg viewBox="0 0 120 80" class="h-9 skew-x-12" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M42 8h36v18a18 18 0 0 1-36 0z" /><path d="M42 13H30v5a13 13 0 0 0 13 13M78 13h12v5a13 13 0 0 1-13 13" /><path d="M60 44v12M46 70h28M50 70l2-14h16l2 14" />
                        </svg>
                    </span>

                    <span class="min-w-0">
                        <span class="block truncate font-display text-2xl font-extrabold uppercase italic leading-tight text-white">{{ $cup->name }}</span>
                        <span class="mt-2 flex flex-wrap gap-1.5 font-mono text-[10px] tracking-[0.1em]">
                            <span class="rounded-[2px] bg-white/10 px-1.5 py-0.5 text-white/80">{{ $cup->participants_count }} EQUIPOS</span>
                            <span class="rounded-[2px] bg-white/10 px-1.5 py-0.5 text-white/80">{{ $cup->has_group_stage ? 'CON FASE DE GRUPOS' : 'ELIMINATORIA DIRECTA' }}</span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
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
