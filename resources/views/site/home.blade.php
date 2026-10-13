<x-layouts.site :title="'Amazon Toernooien — La liga de un vistazo'">
    @php
        // Con una sola noticia publicada, una rejilla de tres columnas la deja
        // encogida en un tercio y parece que falta algo. Las clases van
        // literales porque Tailwind lee el fichero, no lo ejecuta: una cadena
        // compuesta no generaría nada.
        $newsGrid = match ($news->count()) {
            0, 1 => '',
            2 => 'sm:grid-cols-2',
            default => 'sm:grid-cols-2 lg:grid-cols-3',
        };

        $percent = $progress['games'] > 0 ? round($progress['played'] / $progress['games'] * 100) : 0;

        // Los dos rótulos se arman aquí y no con un `@if` pegado al texto:
        // Blade sólo reconoce una directiva cuando NO hay frontera de palabra
        // antes de la arroba, así que «JORNADA@if» se queda como texto literal
        // mientras su «@endif» sí compila, y la vista entera revienta con un
        // «unexpected token endif». Costó un 500 averiguarlo.
        $cabecera = mb_strtoupper($season->name).($matchday ? ' · JORNADA '.$matchday->number : '');
        $proxima = 'PRÓXIMA JORNADA'.($matchday?->date ? ' · '.$matchday->date->format('d/m/Y') : '');
    @endphp

    {{-- La bienvenida: el nombre de la liga en grande y dónde estamos. El botón
         de la cabecera trae aquí, así que esto es lo primero que ve quien
         llega. --}}
    <header class="mb-8">
        <h1 class="font-display text-4xl leading-none uppercase tracking-wide text-ink sm:text-5xl">
            Amazon <span class="font-semibold tracking-[0.1em]">Toernooien</span>
        </h1>

        <div class="mt-2 font-mono text-[11px] tracking-[0.14em] text-ink/60">
            {{ $cabecera }}
        </div>

        @if ($progress['games'] > 0)
            {{-- La magnitud se mide UNA vez, con el largo de la barra; el número
                 va al lado en tinta normal. La pista es el mismo tono más
                 claro, no un gris ajeno, para que el estado se lea de punta a
                 punta. --}}
            <div class="mt-5 max-w-md">
                <div class="mb-1.5 flex items-baseline justify-between gap-3">
                    <span class="font-mono text-[10px] tracking-[0.12em] text-ink/55">LIGA DISPUTADA</span>
                    <span class="font-display text-sm font-bold text-ink">
                        {{ $progress['played'] }} de {{ $progress['games'] }} partidos
                    </span>
                </div>
                <div class="h-[6px] overflow-hidden rounded-full bg-ink/15">
                    <div class="h-full rounded-full bg-ink" style="width: {{ $percent }}%"></div>
                </div>
            </div>
        @endif
    </header>

    {{-- Lo que pasa ahora mismo: qué se juega y qué acaba de pasar, uno al lado
         del otro. --}}
    @if ($upcoming->isNotEmpty() || $results->isNotEmpty())
        <x-site.section-title title="Se juega ahora" kicker="El calendario" :href="route('site.fixtures')" cta="Todos los partidos" />

        <div class="grid items-start gap-6 lg:grid-cols-2">
            @if ($upcoming->isNotEmpty())
                <div>
                    <h3 class="mb-3 font-mono text-[10px] tracking-[0.12em] text-ink/55">
                        {{ $proxima }}
                    </h3>
                    <div class="flex flex-col gap-3">
                        @foreach ($upcoming as $row)
                            <x-site.game-card :game="$row['game']" :label="$row['label']" />
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($results->isNotEmpty())
                <div>
                    <h3 class="mb-3 font-mono text-[10px] tracking-[0.12em] text-ink/55">ÚLTIMOS RESULTADOS</h3>
                    <div class="flex flex-col gap-3">
                        @foreach ($results as $game)
                            <x-site.game-card :game="$game" />
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Cómo va la liga: los tres primeros de cada división, con la tabla
         entera a un clic. --}}
    @if ($podiums !== [])
        <x-site.section-title title="Cómo va la liga" kicker="Lo más alto de cada división"
                              :href="route('site.standings')" cta="Clasificación completa" />

        <div class="grid items-start gap-6 lg:grid-cols-2">
            @foreach ($podiums as $podium)
                <x-site.home-podium :heading="$podium['heading']" :rows="$podium['rows']" />
            @endforeach
        </div>
    @endif

    {{-- Quién destaca: con cara, que es lo que hace que esto no parezca una
         hoja de cálculo. --}}
    <x-site.section-title title="Quién destaca" kicker="Las cabeceras de la liga"
                          :href="route('site.scorers')" cta="Todas las estadísticas" />

    <div class="grid items-stretch gap-6 md:grid-cols-3">
        @foreach ($leaders as $leader)
            <x-site.home-leader :label="$leader['label']" :rows="$leader['rows']" />
        @endforeach
    </div>

    {{-- Lo que se comenta: la racha y las cifras de la temporada. --}}
    @if ($form->isNotEmpty())
        <x-site.section-title title="Para comentar" kicker="Rachas y cifras" />

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <x-site.home-form :teams="$form" />
            <x-site.home-stats :highlights="$highlights" />
        </div>
    @endif

    {{-- Las copas, que son la otra competición de la temporada y no caben en
         ninguna tabla. --}}
    @if ($cups->isNotEmpty())
        <x-site.section-title title="Copas" kicker="La otra competición" :href="route('site.cups.index')" />

        <div class="grid gap-3 lg:grid-cols-2">
            @foreach ($cups as $cup)
                <a href="{{ route('site.cups.show', $cup) }}"
                   class="flex items-center gap-4 rounded-[5px] border-l-[3px] border-brand bg-surface-alt p-4 hover:bg-surface-muted">
                    <span class="font-display text-2xl text-brand" aria-hidden="true">&#9819;</span>

                    <span class="min-w-0">
                        <span class="block truncate font-display text-lg font-semibold text-white">{{ $cup->name }}</span>
                        <span class="block font-mono text-[10px] tracking-[0.12em] text-white/45">
                            {{ $cup->participants_count }} EQUIPOS
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($news->isNotEmpty())
        <x-site.section-title title="Noticias" kicker="Lo último" :href="route('site.news.index')" />

        <div class="grid gap-6 {{ $newsGrid }}">
            @foreach ($news as $item)
                <x-site.news-card :item="$item" />
            @endforeach
        </div>
    @endif
</x-layouts.site>
