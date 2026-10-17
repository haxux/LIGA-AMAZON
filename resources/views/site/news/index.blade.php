@php($filtering = $selectedSeason !== null || $selectedClub !== null)

<x-layouts.site :title="'Noticias — Liga Amazon'">
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Noticias</h1>
            <div class="font-mono text-[10px] tracking-[0.14em] text-ink/50">LATEST NEWS</div>
        </div>

        {{-- El filtro, plegado y en voz baja: quien lo busca lo encuentra, y
             quien no, lee las noticias sin ver una barra de controles. --}}
        @if ($seasons->isNotEmpty() || $clubs->isNotEmpty())
            <details class="group relative shrink-0" @if ($filtering) open @endif>
                <summary class="flex cursor-pointer list-none items-center gap-1.5 font-mono text-[10px] tracking-[0.14em] text-ink/45 hover:text-ink [&::-webkit-details-marker]:hidden">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                    FILTRAR
                    @if ($filtering)
                        <span class="size-1.5 rounded-full bg-ink" aria-label="Filtro activo"></span>
                    @endif
                </summary>

                <form method="GET" action="{{ route('site.news.index') }}"
                      class="absolute right-0 z-30 mt-2 w-64 space-y-3 rounded-[6px] bg-surface p-4 shadow-xl">
                    @if ($seasons->isNotEmpty())
                        <label class="block">
                            <span class="mb-1 block font-mono text-[10px] tracking-[0.12em] text-white/50">TEMPORADA</span>
                            <select name="temporada" onchange="this.form.submit()"
                                    class="w-full rounded-[4px] border border-white/10 bg-surface-alt px-3 py-2 text-sm text-white">
                                <option value="">Todas</option>
                                @foreach ($seasons as $season)
                                    <option value="{{ $season->id }}" @selected($selectedSeason?->is($season))>{{ $season->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @if ($clubs->isNotEmpty())
                        <label class="block">
                            <span class="mb-1 block font-mono text-[10px] tracking-[0.12em] text-white/50">EQUIPO</span>
                            <select name="equipo" onchange="this.form.submit()"
                                    class="w-full rounded-[4px] border border-white/10 bg-surface-alt px-3 py-2 text-sm text-white">
                                <option value="">Todos</option>
                                @foreach ($clubs as $club)
                                    <option value="{{ $club->id }}" @selected($selectedClub?->is($club))>{{ $club->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <noscript>
                        <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-sm font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
                    </noscript>

                    @if ($filtering)
                        <a href="{{ route('site.news.index') }}" class="block font-mono text-[10px] tracking-[0.12em] text-white/50 hover:text-white">QUITAR FILTRO</a>
                    @endif
                </form>
            </details>
        @endif
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        @forelse ($items as $item)
            <x-site.news-card :item="$item" />
        @empty
            <p class="text-ink/70">
                {{ $filtering ? 'No hay noticias con ese filtro.' : 'No hay noticias publicadas todavía.' }}
            </p>
        @endforelse
    </div>
</x-layouts.site>
