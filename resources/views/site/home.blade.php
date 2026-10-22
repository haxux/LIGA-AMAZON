<x-layouts.site :title="'Amazon Toernooien — La liga de un vistazo'">
    @php
        $percent = $progress['games'] > 0 ? round($progress['played'] / $progress['games'] * 100) : 0;

        // Los dos rótulos se arman aquí y no con un `@if` pegado al texto:
        // Blade sólo reconoce una directiva cuando NO hay frontera de palabra
        // antes de la arroba, así que «JORNADA@if» se queda como texto literal
        // mientras su «@endif» sí compila, y la vista entera revienta con un
        // «unexpected token endif». Costó un 500 averiguarlo.
        $cabecera = mb_strtoupper($season->name).($jornada ? ' · JORNADA '.$jornada : '');

        // Se nombra la jornada sólo cuando todos los que se enseñan son de
        // ella; si hay un aplazado de otra mezclado, el rótulo no promete una.
        $proxima = $matchday
            ? 'PRÓXIMA JORNADA'.($matchday->date ? ' · '.$matchday->date->format('d/m/Y') : '')
            : 'PRÓXIMOS PARTIDOS';

        // La última noticia va arriba en grande; las demás, al final.
        $lead = $news->first();
        $moreNews = $news->slice(1);

        // Cada cabecera con la ilustración y la tabla de su tema.
        $leaderKinds = ['goles', 'asistencias', 'porterias'];
    @endphp

    {{-- Dónde va la temporada: la jornada en grande y el avance de la liga en
         el medidor de bloques de todo el sitio (veinte, uno por cada 5 %). --}}
    <header class="mb-8 flex flex-wrap items-end justify-between gap-x-8 gap-y-5">
        <div>
            <div class="font-mono text-[11px] tracking-[0.14em] text-ink/70">{{ $cabecera }}</div>
            <h1 class="mt-1 -skew-x-6 font-display text-5xl font-extrabold uppercase italic leading-none tracking-wide text-ink sm:text-6xl">
                {{ $jornada ? 'Jornada '.$jornada : 'Amazon Toernooien' }}
            </h1>
        </div>

        @if ($progress['games'] > 0)
            @php($filled = (int) round($percent / 5))
            <div class="w-full max-w-sm">
                <div class="mb-1.5 flex items-baseline justify-between gap-3">
                    <span class="font-mono text-[10px] tracking-[0.12em] text-ink/70">LIGA DISPUTADA</span>
                    <span class="font-display text-sm font-bold text-ink">{{ $progress['played'] }} de {{ $progress['games'] }} partidos</span>
                </div>
                <div class="flex gap-1" aria-hidden="true">
                    @for ($i = 1; $i <= 20; $i++)
                        <span @class(['h-3 flex-1 -skew-x-12 rounded-[1px]', $i <= $filled ? 'bg-ink' : 'bg-ink/15'])></span>
                    @endfor
                </div>
            </div>
        @endif
    </header>

    {{-- Lo primero: la última noticia en grande y, al lado, la jornada —lo que
         se juega y lo que acaba de pasar— en filas de marcador. --}}
    @if ($lead || $upcoming->isNotEmpty() || $results->isNotEmpty())
        <div @class(['grid items-start gap-6', 'lg:grid-cols-2' => $lead !== null])>
            @if ($lead)
                <x-site.news-feature :item="$lead" large class="aspect-[4/3] lg:aspect-auto lg:h-full lg:min-h-[480px]" />
            @endif

            <div @class(['grid gap-6', 'lg:grid-cols-2' => $lead === null])>
                @if ($upcoming->isNotEmpty())
                    <div>
                        <h2 class="mb-2 font-mono text-[10px] tracking-[0.12em] text-ink/70">{{ $proxima }}</h2>
                        <div class="grid gap-2">
                            @foreach ($upcoming as $game)
                                {{-- La etiqueta la pone la portada porque la fila,
                                     por sí sola, no dice de qué división es. --}}
                                <x-site.match-row :game="$game"
                                    :label="mb_strtoupper('Jornada '.$game->matchday->number.($game->matchday->division ? ' · '.$game->matchday->division->name : ''))" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($results->isNotEmpty())
                    <div>
                        <h2 class="mb-2 font-mono text-[10px] tracking-[0.12em] text-ink/70">ÚLTIMOS RESULTADOS</h2>
                        <div class="grid gap-2">
                            @foreach ($results as $game)
                                <x-site.match-row :game="$game" :label="mb_strtoupper($game->competitionLabel())" />
                            @endforeach
                        </div>
                    </div>
                @endif

                <a href="{{ route('site.fixtures') }}"
                   class="justify-self-start font-display text-sm font-bold uppercase tracking-[0.08em] text-ink/70 hover:text-ink">Todos los partidos</a>
            </div>
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
         hoja de cálculo, y la ilustración de cada tema detrás. --}}
    <x-site.section-title title="Quién destaca" kicker="Las cabeceras de la liga"
                          :href="route('site.scorers')" cta="Todas las estadísticas" />

    <div class="grid items-stretch gap-6 md:grid-cols-3">
        @foreach ($leaders as $leader)
            @php($kind = $leaderKinds[$loop->index] ?? 'goles')
            <x-site.home-leader :label="$leader['label']" :rows="$leader['rows']" :kind="$kind"
                                :href="route('site.scorers').'#'.$kind" />
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
         ninguna tabla. Cada una con su placa de trofeo y, las internacionales,
         con sus colores. --}}
    @if ($cups->isNotEmpty())
        <x-site.section-title title="Copas" kicker="La otra competición" :href="route('site.cups.index')" />

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($cups as $cup)
                <a href="{{ route('site.cups.show', $cup) }}" style="{{ $cup->themeStyle() }}"
                   class="flex items-center gap-4 rounded-md bg-surface p-4 transition-colors hover:bg-surface-alt">
                    <span class="grid size-12 shrink-0 -skew-x-12 place-items-center rounded-[4px] bg-brand text-ink" aria-hidden="true">
                        <svg viewBox="0 0 120 80" class="h-7 skew-x-12" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M42 8h36v18a18 18 0 0 1-36 0z" /><path d="M42 13H30v5a13 13 0 0 0 13 13M78 13h12v5a13 13 0 0 1-13 13" /><path d="M60 44v12M46 70h28M50 70l2-14h16l2 14" />
                        </svg>
                    </span>

                    <span class="min-w-0">
                        <span class="block truncate font-display text-xl font-extrabold uppercase italic text-white">{{ $cup->name }}</span>
                        <span class="block font-mono text-[10px] tracking-[0.12em] text-white/60">
                            {{ $cup->participants_count }} EQUIPOS
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($moreNews->isNotEmpty())
        <x-site.section-title title="Más noticias" kicker="Lo último" :href="route('site.news.index')" />

        {{-- Cuatro en el móvil y en la app (decisión del propietario,
             2026-10-22), que van de una en una o de dos en dos; en escritorio
             la cuarta se esconde, porque la fila es de tres. --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($moreNews as $item)
                <div @class(['contents', 'lg:hidden' => $loop->iteration > 3])>
                    <x-site.news-card :item="$item" />
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.site>
