<x-layouts.site :title="'Equipos — Liga Amazon'">
    <h1 class="font-display mb-1 -skew-x-6 text-4xl font-extrabold uppercase italic tracking-wide text-ink sm:text-5xl">Equipos</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">{{ mb_strtoupper($selectedSeason->name) }}</div>

    {{-- Los mismos filtros que Estadísticas: la temporada en desplegable,
         porque la lista crece cada año, y la división a la vista. --}}
    <div class="mb-8 grid gap-3 rounded-md border-t-4 border-brand bg-surface p-4">
        <form method="GET" action="{{ route('site.clubs.index') }}"
              class="grid gap-1.5 sm:grid-cols-[7.5rem_minmax(0,1fr)] sm:items-center sm:gap-4">
            <label for="filtro-temporada" class="font-mono text-[10px] tracking-[0.12em] text-white/50">TEMPORADA</label>
            <div class="flex gap-2">
                <select id="filtro-temporada" name="temporada" onchange="this.form.submit()"
                        class="w-full rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white focus-visible:outline-2 focus-visible:outline-brand sm:max-w-[260px]">
                    @foreach ($seasons->pluck('name', 'id') as $value => $text)
                        <option value="{{ $value }}" @selected((string) $value === (string) $selectedSeason->id)>{{ $text }}</option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
                </noscript>
            </div>
        </form>

        {{-- Una división a la vez, como las copas: el mismo menú de la
             cabecera, a mano también aquí para el móvil. --}}
        @if ($divisions->count() > 1)
            <x-site.stat-segments label="DIVISIÓN"
                                  :options="['' => 'Todas'] + $divisions->pluck('name', 'id')->all()"
                                  :selected="$selectedDivision ?? ''"
                                  :url="fn ($value) => route('site.clubs.index', array_filter(['temporada' => $selectedSeason->id, 'division' => $value]))" />
        @endif
    </div>

    @forelse ($groups as $group)
        <section class="mb-10">
            <h2 class="mb-4 flex items-center gap-3 rounded-md bg-surface px-4 py-2.5">
                <span class="grid -skew-x-12 place-items-center rounded-[3px] bg-brand px-2.5 py-1 text-ink" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="size-5 skew-x-12" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                        <path d="M12 2.8l7 2.5V11c0 4.7-3 8.1-7 10-4-1.9-7-5.3-7-10V5.3z" />
                    </svg>
                </span>
                <span class="font-display text-lg font-bold uppercase italic tracking-wide text-white">{{ $group['heading'] }}</span>
            </h2>

            {{-- Por su puesto en la tabla: la lista ya cuenta cómo va la liga.
                 El líder lleva el puesto en amarillo. --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($group['teams'] as $entry)
                    @php($team = $entry['team'])
                    <a href="{{ route('site.clubs.show', ['club' => $team->club_id, 'temporada' => $selectedSeason->id]) }}"
                       class="flex items-center gap-4 rounded-md bg-surface p-4 transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-brand">
                        <x-site.club-crest :club="$team->club" size="size-14" />

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-display text-xl font-bold uppercase italic leading-tight text-white">{{ $team->name }}</span>

                            <span class="mt-1.5 flex items-center gap-3">
                                <span class="font-display text-base font-bold tabular-nums text-brand">
                                    {{ $entry['standing']?->points ?? 0 }} <span class="text-xs font-semibold text-white/45">PTS</span>
                                </span>

                                {{-- La racha, de la más antigua a la más reciente. Los
                                     colores son los de estado del sitio y van SIEMPRE con
                                     su letra: sin ella, derrota y empate casi no se
                                     distinguen en visión deuteranope (ver home-form). --}}
                                @if ($entry['form'])
                                    <span class="flex gap-1" aria-label="Últimos partidos: {{ implode(', ', $entry['form']) }}">
                                        @foreach ($entry['form'] as $result)
                                            <span class="grid size-[18px] -skew-x-12 place-items-center rounded-[2px] font-mono text-[9px] font-bold text-white {{ ['V' => 'bg-win', 'E' => 'bg-draw', 'D' => 'bg-loss'][$result] }}"
                                                  title="{{ ['V' => 'Victoria', 'E' => 'Empate', 'D' => 'Derrota'][$result] }}" aria-hidden="true"><span class="skew-x-12">{{ $result }}</span></span>
                                        @endforeach
                                    </span>
                                @endif
                            </span>
                        </span>

                        <span @class([
                                  'shrink-0 -skew-x-8 font-display text-4xl font-extrabold leading-none tabular-nums',
                                  'text-brand' => $entry['position'] === 1,
                                  'text-white/85' => $entry['position'] !== 1,
                              ])
                              title="Puesto {{ $entry['position'] }}">{{ $entry['position'] }}º</span>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            Todavía no hay equipos inscritos en esta temporada.
        </p>
    @endforelse
</x-layouts.site>
