@php
    use App\Models\GameEvent;

    // El vocabulario del modelo está en inglés desde la Fase 2 y el sitio es
    // español, así que las etiquetas viven aquí, como en el detalle de partido.
    $labels = [
        GameEvent::TYPE_GOAL => 'Goles',
        GameEvent::TYPE_ASSIST => 'Asistencias',
        GameEvent::TYPE_YELLOW_CARD => 'Amarillas',
        GameEvent::TYPE_RED_CARD => 'Rojas',
        GameEvent::TYPE_CLEAN_SHEET => 'Porterías a cero',
    ];

    $eventLabels = [
        GameEvent::TYPE_GOAL => 'Gol',
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
        GameEvent::TYPE_ASSIST => 'A',
        GameEvent::TYPE_YELLOW_CARD => 'TA',
        GameEvent::TYPE_RED_CARD => 'TR',
        GameEvent::TYPE_CLEAN_SHEET => 'PC',
    ];
@endphp

<x-layouts.site :title="$player->name.' — Liga Amazon'">
    <header class="mb-6 flex flex-wrap items-center gap-5 rounded-[6px] bg-surface-alt p-5">
        <span class="flex size-16 shrink-0 items-center justify-center rounded-full bg-surface-muted font-display text-2xl font-bold text-brand">
            {{ $shirtNumber ?? '—' }}
        </span>

        <div class="min-w-0">
            <h1 class="font-display text-3xl uppercase tracking-wide text-white">{{ $player->name }}</h1>

            <p class="mt-1 font-mono text-[10px] tracking-[0.12em] text-white/45">
                @if ($club)
                    <a href="{{ route('site.clubs.show', array_filter(['club' => $club->id, 'temporada' => $selectedSeason?->id])) }}"
                       class="hover:text-brand">{{ mb_strtoupper($club->name) }}</a>
                @endif
                · {{ mb_strtoupper($positions[$player->position] ?? $player->position) }}
                @if ($player->specific_position) · {{ $player->specific_position }} @endif
                @if ($player->birth_date) · {{ $player->birth_date->age }} AÑOS ({{ $player->birth_date->format('d/m/Y') }}) @endif
                @if ($player->market_value) · VALOR {{ number_format($player->market_value, 0, ',', '.') }} @endif
            </p>

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
            <form method="GET" action="{{ route('site.players.show', $player) }}" class="ml-auto">
                <label>
                    <span class="mb-1 block font-mono text-[10px] tracking-[0.12em] text-white/50">TEMPORADA</span>
                    <select name="temporada" onchange="this.form.submit()"
                            class="rounded-[4px] border border-white/10 bg-surface px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white">
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
    </header>

    {{-- Separadas por competición (Fase 15, decisión del propietario): primero
         la temporada entera y después cada competición en la que está su
         equipo. Sin copas no hay nada que separar, y va una tanda sola. --}}
    <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">
        {{ $selectedSeason ? 'EN '.mb_strtoupper($selectedSeason->name) : 'ESTADÍSTICAS' }}
    </h2>

    @foreach ($statBlocks as $block)
        <section class="mb-6">
            @if ($block['title'])
                <h3 class="mb-2 font-mono text-[10px] tracking-[0.14em] text-white/45">{{ mb_strtoupper($block['title']) }}</h3>
            @endif

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                @foreach ($labels as $type => $label)
                    <div class="rounded-[5px] bg-surface-alt p-4 text-center">
                        <span class="block font-mono text-[9px] tracking-[0.14em] text-white/40">{{ mb_strtoupper($label) }}</span>
                        <span class="font-display text-3xl font-bold {{ $type === \App\Models\GameEvent::TYPE_GOAL ? 'text-brand' : 'text-white' }}">
                            {{ $block['totals'][$type] ?? 0 }}
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($seasons->count() > 1)
        {{-- El desglose por temporada, con el club de cada una: con una cesión
             no es el mismo que el club propietario. --}}
        <section class="mb-6 overflow-hidden rounded-md bg-surface">
            <h2 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">Por temporada</h2>

            <table class="w-full text-left">
                <thead>
                    <tr class="font-mono text-[9px] tracking-[0.12em] text-white/40">
                        <th class="px-4 py-2">TEMPORADA</th>
                        <th class="px-4 py-2">CLUB</th>
                        @foreach ($short as $label)
                            <th class="px-2 py-2 text-center">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.055]">
                    @foreach ($seasons as $row)
                        <tr class="font-display text-sm text-white {{ $row['season']->id === $selectedSeason?->id ? 'bg-white/[0.04]' : '' }}">
                            <td class="px-4 py-2">{{ $row['season']->name }}</td>
                            <td class="truncate px-4 py-2 text-white/70">{{ $row['club']?->name }}</td>
                            @foreach (array_keys($short) as $type)
                                <td class="px-2 py-2 text-center">{{ $row['totals'][$type] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="font-display text-sm font-bold text-brand">
                        <td class="px-4 py-2">TOTAL</td>
                        <td class="px-4 py-2"></td>
                        @foreach (array_keys($short) as $type)
                            <td class="px-2 py-2 text-center">{{ $careerTotals[$type] ?? 0 }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </section>
    @endif

    <section>
        <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">EVENTOS</h2>

        @forelse ($events as $event)
            <a href="{{ route('site.games.show', $event->game) }}"
               class="mb-2 flex items-center gap-3 rounded-[5px] bg-surface-alt px-4 py-2 hover:bg-surface-muted">
                <span class="w-10 shrink-0 font-mono text-xs text-white/50">
                    {{ $event->minute ? $event->minute."'" : '—' }}
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-base font-semibold text-white">
                        {{ $event->game?->homeTeam?->name }} – {{ $event->game?->awayTeam?->name }}
                    </span>
                    <span class="block font-mono text-[10px] tracking-[0.1em] text-white/45">
                        {{ $eventLabels[$event->type] ?? $event->type }}
                        @if ($event->game) · {{ mb_strtoupper($event->game->competitionLabel()) }} @endif
                    </span>
                </span>
            </a>
        @empty
            <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
                {{ $selectedSeason ? 'Sin eventos en esta temporada.' : 'Sin eventos registrados todavía.' }}
            </p>
        @endforelse
    </section>
</x-layouts.site>
