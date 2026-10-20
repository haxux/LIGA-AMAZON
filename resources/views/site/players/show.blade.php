@php
    use App\Models\GameEvent;

    // El vocabulario del modelo está en inglés desde la Fase 2 y el sitio es
    // español, así que las etiquetas viven aquí, como en el detalle de partido.
    $labels = [
        GameEvent::TYPE_GOAL => 'Goles',
        GameEvent::TYPE_OWN_GOAL => 'Autogoles',
        GameEvent::TYPE_ASSIST => 'Asistencias',
        GameEvent::TYPE_YELLOW_CARD => 'Amarillas',
        GameEvent::TYPE_RED_CARD => 'Rojas',
        GameEvent::TYPE_CLEAN_SHEET => 'Porterías a cero',
        'mvp' => 'MVP del partido',
        'week_mvp' => 'Week MVP',
    ];

    $eventLabels = [
        GameEvent::TYPE_GOAL => 'Gol',
        GameEvent::TYPE_OWN_GOAL => 'Autogol',
        GameEvent::TYPE_ASSIST => 'Asistencia',
        GameEvent::TYPE_YELLOW_CARD => 'Tarjeta amarilla',
        GameEvent::TYPE_RED_CARD => 'Tarjeta roja',
        GameEvent::TYPE_CLEAN_SHEET => 'Portería a cero',
    ];

    $positions = [
        'Goalkeeper' => 'Portero',
        'Defender' => 'Defensa',
        'Midfielder' => 'Centrocampista',
        'Forward' => 'Delantero',
    ];

    $short = [
        GameEvent::TYPE_GOAL => 'G',
        GameEvent::TYPE_OWN_GOAL => 'AG',
        GameEvent::TYPE_ASSIST => 'A',
        GameEvent::TYPE_YELLOW_CARD => 'TA',
        GameEvent::TYPE_RED_CARD => 'TR',
        GameEvent::TYPE_CLEAN_SHEET => 'PC',
        'mvp' => 'MVP',
        'week_mvp' => 'WM',
    ];
@endphp

<x-layouts.site :title="$player->name.' — Liga Amazon'">
    @php
        // Los porteros se miden por lo que evitan, no por lo que marcan: sus
        // dos cifras de cabecera son las porterías a cero y el MVP.
        $isKeeper = $player->position === \App\Models\Player::POSITION_GOALKEEPER;
        $headline = $isKeeper
            ? [GameEvent::TYPE_CLEAN_SHEET, 'mvp']
            : [GameEvent::TYPE_GOAL, GameEvent::TYPE_ASSIST];
    @endphp

    {{-- La cabecera, como la del club: quién es arriba y, debajo, un marcador
         con lo que se busca de un jugador. --}}
    <header class="mb-6 overflow-hidden rounded-md bg-surface">
        <div class="flex flex-wrap items-center gap-4 p-5 sm:gap-5 sm:p-6">
            @if ($player->photo_path)
                {{-- La foto la sube el administrador a mano: ninguna fuente de
                     datos libre las trae. Sin foto se cae al dorsal de siempre. --}}
                <img src="{{ Storage::disk(config('filesystems.uploads'))->url($player->photo_path) }}"
                     alt="" class="size-16 shrink-0 rounded-full object-cover ring-2 ring-brand sm:size-24">
            @else
                <span class="grid size-16 shrink-0 place-items-center rounded-full bg-surface-muted ring-2 ring-brand sm:size-24">
                    <span class="-skew-x-8 font-display text-3xl font-extrabold text-brand sm:text-4xl">{{ $shirtNumber ?? '—' }}</span>
                </span>
            @endif

            <div class="min-w-0 flex-1">
                <h1 class="font-display -skew-x-6 text-3xl font-extrabold uppercase italic leading-none tracking-wide text-white sm:text-5xl">
                    @if ($player->fc_id)
                        {{-- El id de EA FC es el mismo de la ficha en SoFIFA. --}}
                        <a href="https://sofifa.com/player/{{ $player->fc_id }}" target="_blank" rel="noopener noreferrer"
                           title="Ver en SoFIFA" class="hover:text-brand">{{ $player->name }}</a>
                    @else
                        {{ $player->name }}
                    @endif
                </h1>

                @if ($club)
                    <a href="{{ route('site.clubs.show', array_filter(['club' => $club->id, 'temporada' => $selectedSeason?->id])) }}"
                       class="mt-2 inline-flex items-center gap-2 font-mono text-[11px] tracking-[0.12em] text-white/60 hover:text-brand">
                        <x-site.club-crest :club="$club" size="size-5" />
                        {{ mb_strtoupper($club->name) }}
                    </a>
                @endif

                @if ($player->left_at)
                    {{-- Un jugador salido conserva su ficha: sus goles y tarjetas
                         cuelgan de ella (design D9). --}}
                    <p class="mt-1 font-mono text-[10px] tracking-[0.12em] text-rose-400">
                        SALIÓ DE LA LIGA EL {{ $player->left_at->format('d/m/Y') }}
                        @if ($player->left_to) · {{ mb_strtoupper($player->left_to) }} @endif
                    </p>
                @endif
            </div>

            @if ($seasons->isNotEmpty())
                <form method="GET" action="{{ route('site.players.show', $player) }}" class="w-full sm:w-auto">
                    <label>
                        <span class="mb-1 block font-mono text-[10px] tracking-[0.12em] text-white/50">TEMPORADA</span>
                        <select name="temporada" onchange="this.form.submit()"
                                class="w-full rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white focus-visible:outline-2 focus-visible:outline-brand sm:w-auto">
                            @foreach ($seasons as $row)
                                <option value="{{ $row['season']->id }}" @selected($row['season']->id === $selectedSeason?->id)>{{ $row['season']->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <noscript>
                        <button type="submit" class="mt-2 rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Ver</button>
                    </noscript>
                </form>
            @endif
        </div>

        <dl class="grid grid-cols-2 divide-white/[0.06] border-t border-white/[0.06] bg-surface-alt sm:grid-cols-4 sm:divide-x">
            @foreach ([
                ['POSICIÓN', $player->specific_position ?? '—', mb_strtoupper($positions[$player->position] ?? $player->position), false],
                ['DORSAL', $shirtNumber ?? '—', null, false],
                ['EDAD', $player->birth_date?->age ?? '—', $player->birth_date?->format('d/m/Y'), false],
                ['VALOR', $player->market_value ? number_format($player->market_value, 0, ',', '.') : '—', null, true],
            ] as [$label, $value, $note, $lead])
                <div class="px-4 py-3 sm:px-5">
                    <dt class="font-mono text-[9px] tracking-[0.12em] text-white/45 sm:text-[10px]">{{ $label }}</dt>
                    <dd class="mt-0.5 -skew-x-8 truncate font-display text-2xl font-extrabold leading-none tabular-nums sm:text-3xl {{ $lead ? 'text-brand' : 'text-white' }}">{{ $value }}</dd>
                    @if ($note)
                        <dd class="mt-1 font-mono text-[9px] tracking-[0.1em] text-white/45">{{ $note }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </header>

    @if ($player->fcPlayer)
        <x-site.fc-ratings :ficha="$player->fcPlayer" :es-portero="$isKeeper" />
    @endif

    {{-- Separadas por competición (Fase 15, decisión del propietario): primero
         la temporada entera y después cada competición en la que está su
         equipo, todas a la vez y lado a lado para compararlas. Sin copas no hay
         nada que separar, y va una tanda sola. --}}
    <h2 class="mb-3 -skew-x-6 font-display text-2xl font-extrabold uppercase italic tracking-wide text-ink">
        {{ $selectedSeason ? 'EN '.mb_strtoupper($selectedSeason->name) : 'ESTADÍSTICAS' }}
    </h2>

    <div @class([
            'mb-6 grid items-start gap-4',
            'md:grid-cols-2' => count($statBlocks) === 2,
            'md:grid-cols-2 xl:grid-cols-3' => count($statBlocks) > 2,
         ])>
        @foreach ($statBlocks as $block)
            <section class="rounded-md bg-surface p-4">
                @if ($block['title'])
                    <x-site.panel-title>{{ mb_strtoupper($block['title']) }}</x-site.panel-title>
                @endif

                <dl class="grid grid-cols-2 gap-2">
                    @foreach ($headline as $type)
                        <div class="rounded-[5px] bg-surface-alt px-4 py-3">
                            <dt class="font-mono text-[9px] tracking-[0.12em] text-white/45">{{ mb_strtoupper($labels[$type]) }}</dt>
                            <dd class="-skew-x-8 font-display text-5xl font-extrabold leading-none tabular-nums {{ $loop->first ? 'text-brand' : 'text-white' }}">{{ $block['totals'][$type] ?? 0 }}</dd>
                        </div>
                    @endforeach
                </dl>

                <dl class="mt-2 grid grid-cols-2 gap-2">
                    @foreach ($labels as $type => $label)
                        @continue(in_array($type, $headline, true))
                        <div class="rounded-[5px] bg-surface-alt px-3 py-2">
                            <dt class="flex items-center gap-1.5 truncate font-mono text-[9px] tracking-[0.1em] text-white/45">
                                @if ($type === GameEvent::TYPE_YELLOW_CARD) <span class="h-3 w-2 shrink-0 rounded-[1px] bg-[#F5C518]" aria-hidden="true"></span> @endif
                                @if ($type === GameEvent::TYPE_RED_CARD) <span class="h-3 w-2 shrink-0 rounded-[1px] bg-[#D6343F]" aria-hidden="true"></span> @endif
                                {{ mb_strtoupper($label) }}
                            </dt>
                            <dd class="font-display text-xl font-bold tabular-nums text-white">{{ $block['totals'][$type] ?? 0 }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>

    @if ($seasons->count() > 1)
        {{-- El desglose por temporada, con el club de cada una: con una cesión
             no es el mismo que el club propietario. --}}
        <section class="mb-6 rounded-md bg-surface p-4">
            <x-site.panel-title>Por temporada</x-site.panel-title>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left">
                    <thead>
                        <tr class="font-mono text-[9px] tracking-[0.12em] text-white/40">
                            <th class="px-3 py-2">TEMPORADA</th>
                            <th class="px-3 py-2">CLUB</th>
                            @foreach ($short as $type => $label)
                                <th class="px-2 py-2 text-center" title="{{ $labels[$type] }}">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.055]">
                        @foreach ($seasons as $row)
                            @php($current = $row['season']->id === $selectedSeason?->id)
                            <tr class="font-display text-base {{ $current ? 'bg-white/[0.04] text-white' : 'text-white/80' }}">
                                <td class="px-3 py-2 font-bold {{ $current ? 'text-brand' : '' }}">{{ $row['season']->name }}</td>
                                <td class="px-3 py-2">
                                    <span class="flex items-center gap-2">
                                        <x-site.club-crest :club="$row['club']" size="size-5" />
                                        <span class="truncate text-white/70">{{ $row['club']?->name }}</span>
                                    </span>
                                </td>
                                @foreach (array_keys($short) as $type)
                                    <td class="px-2 py-2 text-center tabular-nums">{{ $row['totals'][$type] ?? 0 }}</td>
                                @endforeach
                            </tr>
                        @endforeach

                        <tr class="font-display text-base font-extrabold text-brand">
                            <td class="px-3 py-2">TOTAL</td>
                            <td class="px-3 py-2"></td>
                            @foreach (array_keys($short) as $type)
                                <td class="px-2 py-2 text-center tabular-nums">{{ $careerTotals[$type] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="rounded-md bg-surface p-4">
        <x-site.panel-title>EVENTOS</x-site.panel-title>

        @if ($events->isEmpty())
            <p class="text-sm text-white/55">
                {{ $selectedSeason ? 'Sin eventos en esta temporada.' : 'Sin eventos registrados todavía.' }}
            </p>
        @else
            {{-- Compactos y a dos columnas: una temporada de goles era una
                 columna larguísima de franjas iguales. --}}
            <div class="grid gap-x-6 lg:grid-cols-2">
                @foreach ($events as $event)
                    <a href="{{ route('site.games.show', $event->game) }}"
                       class="flex items-center gap-3 border-b border-white/[0.05] py-2 text-white transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-brand">
                        {{-- El icono en lugar del minuto, igual que en la ficha del
                             partido: un solo lenguaje visual en todo el sitio. --}}
                        <x-event-icon :type="$event->type" :second-yellow="$event->isSecondYellow()" size="size-6" />

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-display text-base font-semibold text-white">
                                {{ $event->game?->homeTeam?->name }} – {{ $event->game?->awayTeam?->name }}
                            </span>
                            <span class="block truncate font-mono text-[10px] tracking-[0.1em] text-white/45">
                                {{ $event->isSecondYellow() ? 'Doble amarilla · expulsado' : ($eventLabels[$event->type] ?? $event->type) }}
                                @if ($event->game) · {{ mb_strtoupper($event->game->competitionLabel()) }} @endif
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.site>
