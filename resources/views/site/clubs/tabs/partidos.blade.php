{{-- El calendario del club, visto desde el club: una fila por partido con el
     rival, si se juega en casa o fuera y cómo acabó —V/E/D con el marcador
     propio delante—, o la fecha si todavía no se jugó. Antes cada jornada
     repetía su nombre dos veces y no decía si se había ganado. --}}

{{-- Empieza por la liga, que es lo que un club mira a diario; la copa se pide.
     Sin copas no hay nada que elegir y los botones no se pintan. --}}
@if ($competitionOptions !== [])
    <div class="mb-6 rounded-md bg-surface p-4">
        <x-site.stat-segments label="COMPETICIÓN" :options="$competitionOptions" :selected="$competition->key"
                              :url="fn ($value) => route('site.clubs.show', array_filter(['club' => $club->id, 'tab' => 'partidos', 'temporada' => $selectedSeason?->id, 'competicion' => $value]))" />
    </div>
@endif

@if ($games->isEmpty())
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        {{ $competition->isAll()
            ? 'Este club todavía no tiene calendario en esta temporada.'
            : 'Este club no tiene partidos de '.$competition->label.' en esta temporada.' }}
    </p>
@else
    @php
        // Mismos colores y letras de racha que el resto del sitio (home-form).
        $tones = ['V' => 'bg-win', 'E' => 'bg-draw', 'D' => 'bg-loss'];
        $names = ['V' => 'Victoria', 'E' => 'Empate', 'D' => 'Derrota'];
    @endphp

    <section class="rounded-md bg-surface p-4">
        {{-- Dos columnas en pantalla ancha: una temporada entera en una sola
             columna era bajar y bajar. --}}
        <ol class="grid gap-x-6 lg:grid-cols-2">
            @foreach ($games as $game)
                @php
                    $home = (int) $game->home_team_id === (int) $team->getKey();
                    $rival = $home ? $game->awayTeam : $game->homeTeam;
                    $played = $game->home_score !== null && $game->away_score !== null;
                    $own = $home ? $game->home_score : $game->away_score;
                    $other = $home ? $game->away_score : $game->home_score;
                    $result = $played ? ($own > $other ? 'V' : ($own === $other ? 'E' : 'D')) : null;
                    $when = $game->kickoff_at ?? $game->matchday?->date;
                    $label = $game->competitionLabel();
                @endphp
                <li class="border-b border-white/[0.05]">
                    <a href="{{ route('site.games.show', $game) }}"
                       class="flex items-center gap-3 py-2.5 transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-brand">
                        {{-- La jornada en corto; la etiqueta entera, que en copa
                             dice la ronda, va debajo del rival. --}}
                        <span class="w-9 shrink-0 -skew-x-8 text-center font-display text-xl font-extrabold tabular-nums text-white/35">
                            {{ $game->matchday?->number ?? '·' }}
                        </span>

                        <x-site.team-crest :team="$rival" size="size-7" />

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-display text-base font-bold text-white">{{ $rival?->name }}</span>
                            <span class="block truncate font-mono text-[10px] tracking-[0.08em] text-white/45">
                                {{ $home ? 'LOCAL' : 'VISITANTE' }} · {{ $label }}
                            </span>
                        </span>

                        @if ($result)
                            <span class="flex shrink-0 items-center gap-2" title="{{ $names[$result] }}">
                                <span class="grid size-6 -skew-x-12 place-items-center rounded-[3px] font-display text-sm font-bold text-white {{ $tones[$result] }}"><span class="skew-x-12">{{ $result }}</span></span>
                                <span class="w-12 -skew-x-8 text-right font-display text-xl font-extrabold tabular-nums text-white">{{ $own }}–{{ $other }}</span>
                            </span>
                        @else
                            <span class="shrink-0 text-right font-mono text-[10px] tracking-[0.08em] text-white/50">
                                {{ $when ? $when->format($game->kickoff_at ? 'd/m H:i' : 'd/m') : 'POR JUGAR' }}
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endif
