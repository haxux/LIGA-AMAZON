@php
    // La URL de la página con un filtro cambiado y los demás como están. Lo
    // que no encaje con lo nuevo (una jornada de grupo de otra copa) lo deja
    // el controlador en su valor por omisión.
    $current = [
        'temporada' => $selectedSeason->id,
        'competicion' => $competition->key,
        'division' => $selectedDivision?->id ?? $all,
        'jornada' => $selectedNumber ?? $all,
        'jornada_grupo' => $selectedGroupMatchday,
    ];
    $filterUrl = fn (string $name) => fn ($value) => route('site.fixtures', array_filter(
        array_merge($current, [$name => $value]),
        fn ($v) => $v !== null && $v !== '',
    ));
@endphp

<x-layouts.site :title="'Partidos — Liga Amazon'">
    <h1 class="font-display mb-1 -skew-x-6 text-4xl font-extrabold uppercase italic tracking-wide text-ink sm:text-5xl">Partidos</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/70">
        {{ mb_strtoupper($selectedSeason->name) }} · {{ mb_strtoupper($competition->isAll() ? 'Todas las competiciones' : $competition->label) }}
    </div>

    {{-- Los filtros de Estadísticas: la temporada en desplegable, porque la
         lista crece cada año; el resto a la vista, y la jornada como una regla
         de números que se desliza en el móvil. --}}
    <div class="mb-8 grid gap-3 rounded-md border-t-4 border-brand bg-surface p-4">
        <form method="GET" action="{{ route('site.fixtures') }}"
              class="grid gap-1.5 sm:grid-cols-[7.5rem_minmax(0,1fr)] sm:items-center sm:gap-4">
            <label for="filtro-temporada" class="font-mono text-[10px] tracking-[0.12em] text-white/60">TEMPORADA</label>
            <div class="flex gap-2">
                <select id="filtro-temporada" name="temporada" onchange="this.form.submit()"
                        class="w-full rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white focus-visible:outline-2 focus-visible:outline-brand sm:max-w-[260px]">
                    @foreach ($seasons->pluck('name', 'id') as $value => $text)
                        <option value="{{ $value }}" @selected((string) $value === (string) $selectedSeason->id)>{{ $text }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="competicion" value="{{ $competition->key }}">
                <noscript>
                    <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
                </noscript>
            </div>
        </form>

        {{-- Sin copas sólo quedarían «Todo» y «Liga», la misma lista dos veces. --}}
        @if (count($competitionOptions) > 2)
            <x-site.stat-segments label="COMPETICIÓN" :options="$competitionOptions"
                                  :selected="$competition->key" :url="$filterUrl('competicion')" />
        @endif

        {{-- Una copa con fase de grupos: sus jornadas de grupo, como la jornada
             de la liga. Las eliminatorias no tienen, y entonces no se pinta. --}}
        @if ($groupMatchdays->isNotEmpty())
            <x-site.stat-segments label="JORNADA DE GRUPO" ruler
                                  :options="collect(['' => 'Todas'])->union($groupMatchdays->mapWithKeys(fn ($number) => [$number => 'Jornada '.$number]))->all()"
                                  :selected="$selectedGroupMatchday ?? ''" :url="$filterUrl('jornada_grupo')" />
        @endif

        {{-- División y jornada son de la liga: con una copa no hay nada que
             elegir. Una sola división tampoco ofrece elección. --}}
        @if ($showLeague)
            @if ($divisions->count() > 1)
                <x-site.stat-segments label="DIVISIÓN"
                                      :options="collect([$all => 'Todas'])->union($divisions->pluck('name', 'id'))->all()"
                                      :selected="$selectedDivision?->id ?? $all" :url="$filterUrl('division')" />
            @endif

            @if ($numbers->isNotEmpty())
                <x-site.stat-segments label="JORNADA" ruler
                                      :options="collect([$all => 'Todas'])->union($numbers->mapWithKeys(fn (int $number) => [$number => 'Jornada '.$number]))->all()"
                                      :selected="$selectedNumber ?? $all" :url="$filterUrl('jornada')" />
            @endif
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
                <span class="font-display text-lg font-bold uppercase italic tracking-wide text-white">{{ $group['division']->name }}</span>
            </h2>

            @foreach ($group['matchdays'] as $matchday)
                <div class="mb-6">
                    <h3 class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <span class="-skew-x-6 font-display text-xl font-extrabold uppercase italic tracking-wide text-ink">Jornada {{ $matchday->number }}</span>
                        @if ($matchday->date)
                            <span class="font-mono text-[10px] tracking-[0.1em] text-ink/70">{{ $matchday->date->format('d/m/Y') }}</span>
                        @endif
                        {{-- El jugador de la jornada, si lo hay, en su placa. --}}
                        @if ($matchday->weekMvp)
                            <a href="{{ route('site.players.show', $matchday->weekMvp) }}"
                               class="inline-grid -skew-x-12 rounded-[3px] bg-ink px-2.5 py-1 hover:bg-surface">
                                <span class="skew-x-12 font-mono text-[10px] tracking-[0.12em] text-brand">★ WEEK MVP · {{ mb_strtoupper($matchday->weekMvp->name) }}</span>
                            </a>
                        @endif
                        <span class="h-0.5 min-w-8 flex-1 bg-ink/15" aria-hidden="true"></span>
                    </h3>

                    @if ($matchday->games->isEmpty())
                        <p class="font-mono text-[11px] tracking-[0.08em] text-ink/70">Sin partidos programados.</p>
                    @else
                        <div class="grid gap-2 lg:grid-cols-2">
                            @foreach ($matchday->games as $game)
                                <x-site.match-row :game="$game" />
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    @empty
        @if ($cupSections->isEmpty())
            <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
                No hay partidos para este filtro.
            </p>
        @endif
    @endforelse

    @foreach ($cupSections as $section)
        <section class="mb-10">
            <h2 style="{{ $section['cup']->themeStyle() }}" class="mb-4 flex items-center gap-3 rounded-md bg-surface px-4 py-2.5">
                <span class="grid -skew-x-12 place-items-center rounded-[3px] bg-brand px-2.5 py-1 text-ink" aria-hidden="true">
                    <svg viewBox="0 0 120 80" class="h-5 skew-x-12" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M42 8h36v18a18 18 0 0 1-36 0z" /><path d="M42 13H30v5a13 13 0 0 0 13 13M78 13h12v5a13 13 0 0 1-13 13" /><path d="M60 44v12M46 70h28M50 70l2-14h16l2 14" />
                    </svg>
                </span>
                <a href="{{ route('site.cups.show', $section['cup']) }}"
                   class="font-display text-lg font-bold uppercase italic tracking-wide text-white hover:text-brand">{{ $section['cup']->name }}</a>
            </h2>

            <div class="grid gap-2 lg:grid-cols-2">
                @foreach ($section['games'] as $game)
                    <x-site.match-row :game="$game" :label="$game->competitionLabel()" />
                @endforeach
            </div>
        </section>
    @endforeach

    <script>
        // La jornada elegida, a la vista en la regla aunque sea la 30.
        document.querySelectorAll('[data-stat-ruler] [aria-current]').forEach(function (active) {
            const ruler = active.parentElement;
            ruler.scrollLeft = active.offsetLeft - (ruler.clientWidth - active.offsetWidth) / 2;
        });
    </script>
</x-layouts.site>
