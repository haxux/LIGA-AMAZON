{{-- Por competición y no sólo por jornada: desde la Fase 15 un club juega
     también partidos de copa, que no tienen jornada ninguna. --}}
@php($byMatchday = $games->groupBy(fn ($game) => $game->competitionLabel()))

{{-- Empieza por la liga, que es lo que un club mira a diario; la copa se pide.
     Sin copas no hay nada que elegir y el selector no se pinta. --}}
@if ($competitionOptions !== [])
    <form method="GET" action="{{ route('site.clubs.show', ['club' => $club->id, 'tab' => 'partidos']) }}"
          class="mb-6 flex flex-wrap items-end gap-4 rounded-md bg-surface p-4">
        {{-- La temporada elegida viaja escondida: el selector de arriba es otro
             formulario, y sin esto cambiar de competición devolvería a la vigente. --}}
        @if ($selectedSeason)
            <input type="hidden" name="temporada" value="{{ $selectedSeason->id }}">
        @endif

        <x-site.filter-select name="competicion" label="COMPETICIÓN"
                              :options="$competitionOptions" :selected="$competition->key" />

        <noscript>
            <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
        </noscript>
    </form>
@endif

@if ($byMatchday->isEmpty())
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        {{ $competition->isAll()
            ? 'Este club todavía no tiene calendario en esta temporada.'
            : 'Este club no tiene partidos de '.$competition->label.' en esta temporada.' }}
    </p>
@else
    {{-- Tres jornadas por fila y las demás debajo. Un club juega un partido por
         jornada, así que apilarlas a lo ancho de la página dejaba una tarjeta
         sola por línea y el calendario entero en vertical. --}}
    <div class="grid gap-x-4 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($byMatchday as $label => $matchdayGames)
            <div>
                <h3 class="mb-2 font-mono text-xs uppercase tracking-[0.1em] text-ink/70">
                    {{ $label }}
                </h3>

                {{-- Dentro de una jornada los partidos se apilan: la columna ya
                     es estrecha, y un club rara vez juega dos el mismo día. --}}
                <div class="grid gap-3">
                    @foreach ($matchdayGames as $game)
                        <x-site.game-card :game="$game" :label="$label" />
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
