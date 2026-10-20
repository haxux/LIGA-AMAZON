<x-layouts.site :title="$club->name.' — Liga Amazon'">
    {{-- Cabecera de la ficha, como una portada: lo permanente del club a la
         izquierda, la temporada a la derecha y, debajo, el marcador de esa
         temporada —puesto, puntos, partidos y diferencia—, que es lo primero
         que se busca de un club y por eso se ve en todas las pestañas.

         Sin el escudo en grande de fondo: se probó, y los escudos que traen su
         propio fondo opaco dejaban un recuadro a la vista. --}}
    <header class="relative mb-4 overflow-hidden rounded-md bg-surface">
        <div class="relative flex flex-wrap items-center gap-4 p-5 sm:gap-5 sm:p-6">
            <x-site.club-crest :club="$club" size="size-16 sm:size-[88px]" />

            <div class="min-w-0 flex-1">
                <h1 class="font-display -skew-x-6 text-3xl font-extrabold uppercase italic leading-none tracking-wide text-white sm:text-5xl">{{ $club->name }}</h1>
                <p class="mt-2 font-mono text-[10px] tracking-[0.12em] text-white/50">
                    {{ mb_strtoupper($club->short_name) }}
                    @if ($club->founded_year) · FUNDADO EN {{ $club->founded_year }} @endif
                    @if ($club->stadium) · {{ mb_strtoupper($club->stadium->name) }} @endif
                    @if ($team?->division) · {{ mb_strtoupper($team->division->name) }} @endif
                </p>
            </div>

            @if ($seasons->isNotEmpty())
                <form method="GET" action="{{ route('site.clubs.show', ['club' => $club->id, 'tab' => $tab]) }}" class="w-full sm:w-auto">
                    <label>
                        <span class="mb-1 block font-mono text-[10px] tracking-[0.12em] text-white/50">TEMPORADA</span>
                        <select name="temporada" onchange="this.form.submit()"
                                class="w-full rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white focus-visible:outline-2 focus-visible:outline-brand sm:w-auto">
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
        </div>

        @if ($standing)
            @php($row = $standing['row'])
            <dl class="relative grid grid-cols-4 divide-x divide-white/[0.06] border-t border-white/[0.06] bg-surface-alt">
                @foreach ([
                    ['PUESTO', $standing['position'].'º', true],
                    ['PUNTOS', $row->points, false],
                    ['JUGADOS', $row->played, false],
                    ['DIF. GOLES', ($row->goal_difference > 0 ? '+' : '').$row->goal_difference, false],
                ] as [$label, $value, $lead])
                    <div class="px-3 py-3 sm:px-5">
                        <dt class="font-mono text-[9px] tracking-[0.12em] text-white/45 sm:text-[10px]">{{ $label }}</dt>
                        <dd class="mt-0.5 -skew-x-8 font-display text-2xl font-extrabold leading-none tabular-nums sm:text-4xl {{ $lead ? 'text-brand' : 'text-white' }}">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </header>

    {{-- Las pestañas, inclinadas como los filtros de Estadísticas. En el móvil
         no saltan de línea: se deslizan de lado. --}}
    <nav class="mb-6 flex gap-1.5 overflow-x-auto rounded-md bg-ink px-2 py-1.5" aria-label="Secciones del club" data-club-tabs>
        @foreach ($tabs as $key => $label)
            <a href="{{ route('site.clubs.show', array_filter(['club' => $club->id, 'tab' => $key, 'temporada' => $selectedSeason?->id])) }}"
               @if ($tab === $key) aria-current="page" @endif
               @class([
                   'shrink-0 -skew-x-12 rounded-[3px] px-4 py-2 font-display text-base font-bold uppercase italic tracking-[0.06em] transition-colors',
                   'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand',
                   'bg-brand text-ink' => $tab === $key,
                   'text-white/65 hover:bg-white/[0.06] hover:text-white' => $tab !== $key,
               ])>
                <span class="inline-block skew-x-12">{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    <script>
        // La pestaña abierta, a la vista en la tira aunque sea la última.
        (function () {
            const active = document.querySelector('[data-club-tabs] [aria-current]');
            if (active) {
                const strip = active.parentElement;
                strip.scrollLeft = active.offsetLeft - (strip.clientWidth - active.offsetWidth) / 2;
            }
        })();
    </script>

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
