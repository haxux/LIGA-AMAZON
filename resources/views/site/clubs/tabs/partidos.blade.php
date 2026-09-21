{{-- Por competición y no sólo por jornada: desde la Fase 15 un club juega
     también partidos de copa, que no tienen jornada ninguna. --}}
@php($byMatchday = $games->groupBy(fn ($game) => $game->competitionLabel()))

@if ($byMatchday->isEmpty())
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        Este club todavía no tiene calendario en esta temporada.
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
