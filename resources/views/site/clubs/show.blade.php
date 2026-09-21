<x-layouts.site :title="$club->name.' — Liga Amazon'">
    {{-- Cabecera de la ficha: lo permanente del club a la izquierda, lo que
         depende de la temporada elegida a la derecha. --}}
    <header class="mb-6 flex flex-wrap items-center gap-5 rounded-[6px] bg-surface-alt p-5">
        <x-site.club-crest :club="$club" size="size-[64px]" />

        <div class="min-w-0">
            <h1 class="font-display text-3xl uppercase tracking-wide text-white">{{ $club->name }}</h1>
            <p class="mt-1 font-mono text-[10px] tracking-[0.12em] text-white/45">
                {{ $club->short_name }}
                @if ($club->founded_year) · FUNDADO EN {{ $club->founded_year }} @endif
                @if ($club->stadium) · {{ mb_strtoupper($club->stadium->name) }} @endif
                @if ($team?->division) · {{ mb_strtoupper($team->division->name) }} @endif
            </p>
        </div>

        @if ($seasons->isNotEmpty())
            <form method="GET" action="{{ route('site.clubs.show', ['club' => $club->id, 'tab' => $tab]) }}" class="ml-auto">
                <label>
                    <span class="mb-1 block font-mono text-[10px] tracking-[0.12em] text-white/50">TEMPORADA</span>
                    <select name="temporada" onchange="this.form.submit()"
                            class="rounded-[4px] border border-white/10 bg-surface px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white">
                        @foreach ($seasons as $season)
                            <option value="{{ $season->id }}" @selected($season->id === $selectedSeason?->id)>{{ $season->name }}</option>
                        @endforeach
                    </select>
                </label>

                <noscript>
                    <button type="submit" class="mt-2 rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Ver</button>
                </noscript>
            </form>
        @endif
    </header>

    <nav class="mb-6 flex flex-wrap gap-1 border-b border-white/10">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('site.clubs.show', array_filter(['club' => $club->id, 'tab' => $key, 'temporada' => $selectedSeason?->id])) }}"
               class="border-b-[3px] px-4 py-2 font-display text-base font-semibold uppercase tracking-[0.08em] {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-ink/60 hover:text-ink' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($team === null && ! in_array($tab, ['trofeos', 'tecnico'], true))
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            {{ $selectedSeason === null
                ? 'Este club todavía no ha jugado ninguna temporada.'
                : 'Este club no está inscrito en '.$selectedSeason->name.'.' }}
        </p>
    @else
        @include('site.clubs.tabs.'.$tab)
    @endif
</x-layouts.site>
