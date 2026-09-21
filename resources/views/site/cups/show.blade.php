<x-layouts.site :title="$cup->name.' — Liga Amazon'">
    <div class="mb-4 font-mono text-[10px] tracking-[0.14em] text-ink/50">
        <a href="{{ route('site.cups.index') }}" class="hover:text-ink">COPAS</a>
        @if ($cup->season) · {{ mb_strtoupper($cup->season->name) }} @endif
    </div>

    <h1 class="font-display mb-6 text-3xl uppercase tracking-wide text-ink">{{ $cup->name }}</h1>

    {{-- Los grupos, si los lleva: cada uno es una liga pequeña, y se clasifica
         con el mismo código que una división. --}}
    @if ($groups->isNotEmpty())
        <h2 class="font-display mb-3 text-lg uppercase tracking-wide text-ink/70">Fase de grupos</h2>

        <div class="mb-10 grid gap-4 lg:grid-cols-2">
            @foreach ($groups as $entry)
                <section class="overflow-hidden rounded-md bg-surface">
                    <h3 class="font-display bg-surface-alt px-4 py-2 text-base uppercase tracking-wide text-brand">
                        Grupo {{ $entry['group']->name }}
                    </h3>

                    <table class="w-full text-left">
                        <thead>
                            <tr class="font-mono text-[9px] tracking-[0.12em] text-white/40">
                                <th class="px-4 py-2">#</th>
                                <th class="px-2 py-2">EQUIPO</th>
                                @foreach (['PJ', 'G', 'E', 'P', 'DG', 'PTS'] as $head)
                                    <th class="px-2 py-2 text-center">{{ $head }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.055]">
                            @foreach ($entry['table'] as $index => $row)
                                <tr class="font-display text-sm text-white">
                                    <td class="px-4 py-2 text-white/40">{{ $index + 1 }}</td>
                                    <td class="truncate px-2 py-2">
                                        <a href="{{ route('site.clubs.show', ['club' => $row->team->club_id]) }}" class="hover:text-brand">
                                            {{ $row->team->name }}
                                        </a>
                                    </td>
                                    <td class="px-2 py-2 text-center">{{ $row->played }}</td>
                                    <td class="px-2 py-2 text-center">{{ $row->won }}</td>
                                    <td class="px-2 py-2 text-center">{{ $row->drawn }}</td>
                                    <td class="px-2 py-2 text-center">{{ $row->lost }}</td>
                                    <td class="px-2 py-2 text-center">{{ $row->goal_difference }}</td>
                                    <td class="px-2 py-2 text-center font-bold text-brand">{{ $row->points }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
    @endif

    {{-- El cuadro. Cada cruce enseña su global y quién pasó; si pasó por una
         decisión, se dice por qué, que es lo que nadie recuerda después. --}}
    <h2 class="font-display mb-3 text-lg uppercase tracking-wide text-ink/70">Cuadro</h2>

    @forelse ($rounds as $entry)
        <section class="mb-8">
            <h3 class="font-display mb-3 rounded-[4px] bg-surface-alt px-4 py-2 text-lg uppercase tracking-wide text-brand">
                {{ $entry['round']->name }}
                <span class="font-mono text-[10px] tracking-[0.12em] text-white/40">
                    · {{ $entry['round']->isTwoLegged() ? 'IDA Y VUELTA' : 'PARTIDO ÚNICO' }}
                </span>
            </h3>

            @forelse ($entry['ties'] as $item)
                @php($tie = $item['tie'])
                @php($result = $item['result'])

                <div class="mb-3 rounded-[5px] bg-surface-alt p-4">
                    <div class="flex items-center justify-between gap-3">
                        @foreach ([['team' => $tie->homeTeam, 'goals' => $result->homeGoals], ['team' => $tie->awayTeam, 'goals' => $result->awayGoals]] as $index => $side)
                            @if ($index === 1)
                                <span class="font-mono text-[10px] tracking-[0.12em] text-white/35">GLOBAL</span>
                            @endif

                            <span class="flex min-w-0 flex-1 items-center gap-2 {{ $index === 1 ? 'justify-end text-right' : '' }}">
                                @if ($index === 1)
                                    <span class="font-display text-2xl font-bold {{ $result->winner?->is($side['team']) ? 'text-brand' : 'text-white/70' }}">
                                        {{ $result->played === 0 ? '–' : $side['goals'] }}
                                    </span>
                                @endif

                                <a href="{{ route('site.clubs.show', ['club' => $side['team']?->club_id]) }}"
                                   class="truncate font-display text-lg {{ $result->winner?->is($side['team']) ? 'font-bold text-white' : 'text-white/70' }}">
                                    {{ $side['team']?->name }}
                                </a>

                                @if ($index === 0)
                                    <span class="font-display text-2xl font-bold {{ $result->winner?->is($side['team']) ? 'text-brand' : 'text-white/70' }}">
                                        {{ $result->played === 0 ? '–' : $side['goals'] }}
                                    </span>
                                @endif
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-white/10 pt-2 font-mono text-[10px] tracking-[0.1em] text-white/45">
                        @foreach ($tie->games as $game)
                            <a href="{{ route('site.games.show', $game) }}" class="hover:text-brand">
                                {{ $game->homeTeam?->short_name }}
                                {{ $game->home_score ?? '–' }}–{{ $game->away_score ?? '–' }}
                                {{ $game->awayTeam?->short_name }}
                            </a>
                        @endforeach

                        @if ($result->isDecided())
                            <span class="text-brand">PASA {{ mb_strtoupper($result->winner->name) }}</span>
                            @if ($result->note) <span class="text-white/60">· {{ $result->note }}</span> @endif
                        @elseif ($result->needsDecision())
                            <span class="text-amber-400">EMPATE — PENDIENTE DE RESOLVER</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="font-mono text-[11px] tracking-[0.08em] text-ink/50">Todavía sin emparejamientos.</p>
            @endforelse
        </section>
    @empty
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            Esta copa todavía no tiene rondas.
        </p>
    @endforelse
</x-layouts.site>
