@php
    $filtering = $selectedSeason !== null || $selectedClub !== null;

    // El sitio está en inglés para Laravel (`app.locale`), así que el mes en
    // español va aquí y no con `translatedFormat`.
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $longDate = fn ($date) => $date->day.' de '.$months[$date->month - 1].($date->year !== now()->year ? ' de '.$date->year : '');

    // La portada: las tres más recientes en grande, cuando hay bastantes para
    // que el resto no quede en nada. Las demás, agrupadas por día, que es
    // como llegan: en tandas.
    $featured = $items->count() >= 4 ? $items->take(3) : collect();
    $byDay = $items->slice($featured->count())
        ->groupBy(fn ($item) => $item->published_at?->toDateString() ?? '');

    $url = fn (array $change) => route('site.news.index', array_filter(
        array_merge(['temporada' => $selectedSeason?->id, 'equipo' => $selectedClub?->id], $change),
        fn ($value) => $value !== null && $value !== '',
    ));
@endphp

<x-layouts.site :title="'Noticias — Liga Amazon'">
    <h1 class="font-display mb-1 -skew-x-6 text-4xl font-extrabold uppercase italic tracking-wide text-ink sm:text-5xl">Noticias</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">
        {{ $selectedClub ? mb_strtoupper($selectedClub->name) : 'TODA LA LIGA' }}
        @if ($selectedSeason) · {{ mb_strtoupper($selectedSeason->name) }} @endif
    </div>

    {{-- Los filtros, a la vista (decisión del propietario, 2026-10-20; antes
         iban plegados): la temporada en desplegable y los clubes como una
         fila de escudos que se tocan. Sólo se ofrece lo que tiene noticias. --}}
    @if ($seasons->isNotEmpty() || $clubs->isNotEmpty())
        <div class="mb-8 grid gap-3 rounded-md border-t-4 border-brand bg-surface p-4">
            @if ($seasons->isNotEmpty())
                <form method="GET" action="{{ route('site.news.index') }}"
                      class="grid gap-1.5 sm:grid-cols-[7.5rem_minmax(0,1fr)] sm:items-center sm:gap-4">
                    <label for="filtro-temporada" class="font-mono text-[10px] tracking-[0.12em] text-white/50">TEMPORADA</label>
                    <div class="flex gap-2">
                        <select id="filtro-temporada" name="temporada" onchange="this.form.submit()"
                                class="w-full rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white focus-visible:outline-2 focus-visible:outline-brand sm:max-w-[260px]">
                            <option value="">Todas</option>
                            @foreach ($seasons as $season)
                                <option value="{{ $season->id }}" @selected($selectedSeason?->is($season))>{{ $season->name }}</option>
                            @endforeach
                        </select>
                        @if ($selectedClub)
                            <input type="hidden" name="equipo" value="{{ $selectedClub->id }}">
                        @endif
                        <noscript>
                            <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
                        </noscript>
                    </div>
                </form>
            @endif

            @if ($clubs->isNotEmpty())
                <div class="grid gap-1.5 sm:grid-cols-[7.5rem_minmax(0,1fr)] sm:items-center sm:gap-4">
                    <span class="font-mono text-[10px] tracking-[0.12em] text-white/50">CLUB</span>
                    <nav class="flex flex-wrap gap-1.5" aria-label="Filtrar por club">
                        <a href="{{ $url(['equipo' => null]) }}"
                           @if (! $selectedClub) aria-current="true" @endif
                           @class([
                               'grid h-10 -skew-x-12 place-items-center rounded-[3px] px-3 font-display text-sm font-bold uppercase italic tracking-[0.06em] transition-colors focus-visible:outline-2 focus-visible:outline-brand',
                               'bg-brand text-ink' => ! $selectedClub,
                               'bg-surface-alt text-white/60 hover:text-white' => $selectedClub,
                           ])><span class="skew-x-12">Todos</span></a>
                        @foreach ($clubs as $club)
                            @php($active = $selectedClub?->is($club))
                            <a href="{{ $url(['equipo' => $club->id]) }}" title="{{ $club->name }}" aria-label="{{ $club->name }}"
                               @if ($active) aria-current="true" @endif
                               @class([
                                   'grid size-10 place-items-center rounded-[5px] transition-colors focus-visible:outline-2 focus-visible:outline-brand',
                                   'bg-brand' => $active,
                                   'bg-surface-alt hover:bg-surface-muted' => ! $active,
                               ])>
                                <x-site.club-crest :club="$club" size="size-7" />
                            </a>
                        @endforeach
                    </nav>
                </div>
            @endif

            @if ($filtering)
                <a href="{{ route('site.news.index') }}" class="justify-self-start font-mono text-[10px] tracking-[0.12em] text-white/50 hover:text-white">QUITAR FILTRO</a>
            @endif
        </div>
    @endif

    @if ($items->isEmpty())
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            {{ $filtering ? 'No hay noticias con ese filtro.' : 'No hay noticias publicadas todavía.' }}
        </p>
    @endif

    {{-- La portada: la última en grande, con el título en una placa sólida
         sobre la foto (sin degradado), y las dos siguientes al lado. --}}
    @if ($featured->isNotEmpty())
        <div class="mb-10 grid gap-4 lg:grid-cols-3 lg:grid-rows-2">
            @foreach ($featured as $item)
                @php($club = $item->team?->club)
                <a href="{{ route('site.news.show', $item->slug) }}"
                   @class([
                       'group relative block overflow-hidden rounded-md bg-surface-muted focus-visible:outline-2 focus-visible:outline-brand',
                       'aspect-[4/3] lg:col-span-2 lg:row-span-2 lg:aspect-auto lg:min-h-[460px]' => $loop->first,
                       'aspect-[16/10] lg:aspect-auto' => ! $loop->first,
                   ])>
                    @if ($item->cover_path)
                        <img src="{{ Storage::disk(config('filesystems.uploads'))->url($item->cover_path) }}" alt=""
                             class="absolute inset-0 size-full object-cover object-top">
                    @else
                        <div class="bg-hatch absolute inset-0"></div>
                    @endif

                    <span @class([
                        'absolute bottom-0 left-0 max-w-[92%] rounded-tr-md bg-ink transition-colors group-hover:bg-surface',
                        'px-5 py-4' => $loop->first,
                        'px-4 py-3' => ! $loop->first,
                    ])>
                        <span class="mb-1.5 flex items-center gap-2 font-mono text-[10px] tracking-[0.1em] text-brand">
                            @if ($club)
                                <x-site.club-crest :club="$club" size="size-4" />
                                {{ mb_strtoupper($club->name) }}
                            @else
                                LIGA
                            @endif
                            <span class="text-white/40">· {{ $item->published_at?->format('d/m/Y') }}</span>
                        </span>
                        <span @class([
                            'block font-display font-extrabold uppercase italic leading-[1.05] text-white',
                            'text-3xl sm:text-4xl' => $loop->first,
                            'text-xl' => ! $loop->first,
                        ])>{{ $item->title }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    @foreach ($byDay as $day => $dayItems)
        <section class="mb-10">
            <h2 class="mb-4 flex items-center gap-3 font-display text-xl font-extrabold uppercase italic tracking-wide text-ink">
                <span class="-skew-x-6">{{ $day !== '' ? $longDate(\Illuminate\Support\Carbon::parse($day)) : 'Sin fecha' }}</span>
                <span class="h-0.5 flex-1 bg-ink/15" aria-hidden="true"></span>
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($dayItems as $item)
                    <x-site.news-card :item="$item" />
                @endforeach
            </div>
        </section>
    @endforeach
</x-layouts.site>
