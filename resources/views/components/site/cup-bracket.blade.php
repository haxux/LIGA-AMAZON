{{-- El cuadro de una copa como un cuadro de verdad: una columna por ronda
     (cuartos → semifinales → final), cada cruce en una tarjeta compacta y, al
     final, el campeón cuando la final está decidida. En el móvil las columnas
     se deslizan de lado.

     Una ronda todavía sin emparejar enseña sus casillas «Por definir»: tantas
     como cruces le tocan por su sitio en el cuadro (la final uno, las semis
     dos…), en vez de una línea de texto.

     Quien pasa no se escribe: su nombre brilla (`tie-winner`, ver app.css). --}}
@props(['rounds', 'intl' => false])

@php
    $rounds = collect($rounds)->values();
    $total = $rounds->count();

    // El campeón: el ganador de la última ronda, si es un solo cruce decidido.
    $last = $rounds->last();
    $finalTie = $last && count($last['ties']) === 1 ? collect($last['ties'])->first() : null;
    $champion = $finalTie && $finalTie['result']->isDecided() ? $finalTie['result']->winner : null;
@endphp

<div class="-mx-1 overflow-x-auto overscroll-x-contain px-1 pb-2" data-cup-bracket>
    <div class="flex min-w-max gap-4 lg:min-w-0">
        @foreach ($rounds as $index => $entry)
            @php
                $ties = collect($entry['ties']);
                $slots = $ties->isEmpty() ? 2 ** max(0, $total - 1 - $index) : 0;
            @endphp

            <section class="flex w-64 shrink-0 flex-col lg:w-auto lg:min-w-0 lg:flex-1">
                <h3 class="mb-3 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span class="inline-grid -skew-x-12 rounded-[3px] bg-black/85 px-2.5 py-1">
                        <span class="skew-x-12 font-display text-base font-bold uppercase italic tracking-wide text-brand">{{ $entry['round']->name }}</span>
                    </span>
                    <span class="font-mono text-[9px] tracking-[0.12em] text-ink/60">{{ $entry['round']->isTwoLegged() ? 'IDA Y VUELTA' : 'PARTIDO ÚNICO' }}</span>
                </h3>

                {{-- Los cruces repartidos a lo alto de la columna, como en un
                     cuadro: los de la ronda siguiente caen entre los de esta. --}}
                <div class="flex flex-1 flex-col justify-around gap-3">
                    @foreach ($ties as $item)
                        @php
                            $tie = $item['tie'];
                            $result = $item['result'];
                            $legs = $item['legs'];
                            $sides = [
                                ['team' => $tie->homeTeam, 'goals' => $result->homeGoals],
                                ['team' => $tie->awayTeam, 'goals' => $result->awayGoals],
                            ];
                        @endphp

                        <article class="overflow-hidden rounded-md bg-surface {{ $intl ? 'border border-brand/25' : '' }}">
                            @foreach ($sides as $side)
                                @php($won = $result->isDecided() && $result->winner?->is($side['team']))
                                @php($lost = $result->isDecided() && ! $won)
                                <a href="{{ route('site.clubs.show', ['club' => $side['team']?->club_id]) }}"
                                   class="flex items-center gap-2.5 px-3 py-2 {{ $loop->first ? 'border-b border-white/[0.06]' : '' }} hover:bg-surface-alt">
                                    @if ($side['team'])
                                        <x-site.team-crest :team="$side['team']" size="size-6" />
                                    @endif
                                    <span class="min-w-0 flex-1 truncate font-display text-base {{ $won ? 'tie-winner' : ($lost ? 'text-white/45' : 'text-white') }}">{{ $side['team']?->name }}</span>
                                    <span class="w-7 -skew-x-8 text-right font-display text-xl font-extrabold tabular-nums {{ $won ? 'text-brand' : ($lost ? 'text-white/45' : 'text-white') }}">{{ $result->played === 0 ? '–' : $side['goals'] }}</span>
                                </a>
                            @endforeach

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-black/30 px-3 py-1.5 font-mono text-[9px] tracking-[0.12em] text-white/50">
                                <span class="text-white/40">{{ $legs->count() > 1 ? 'GLOBAL' : 'RESULTADO' }}</span>
                                @if ($legs->count() > 1)
                                    @foreach ($legs as $leg)
                                        <a href="{{ route('site.games.show', $leg['game']) }}" class="hover:text-brand">
                                            {{ mb_strtoupper($leg['label']) }} <span class="font-display text-[11px] font-semibold tracking-normal text-white/80">{{ $leg['home'] ?? '–' }} – {{ $leg['away'] ?? '–' }}</span>
                                        </a>
                                    @endforeach
                                @elseif ($legs->isNotEmpty())
                                    <a href="{{ route('site.games.show', $legs->first()['game']) }}" class="hover:text-brand">VER PARTIDO</a>
                                @endif
                            </div>

                            {{-- Por qué pasó, si pasó por una decisión (penaltis,
                                 sorteo…): es lo que nadie recuerda después. --}}
                            @if ($result->isDecided() && $result->note)
                                <div class="bg-black/30 px-3 pb-1.5 font-mono text-[9px] tracking-[0.1em] text-white/55">{{ $result->note }}</div>
                            @elseif ($result->needsDecision())
                                <div class="bg-black/30 px-3 pb-1.5 font-mono text-[9px] tracking-[0.1em] text-amber-400">EMPATE — PENDIENTE DE RESOLVER</div>
                            @endif
                        </article>
                    @endforeach

                    @for ($i = 0; $i < $slots; $i++)
                        <div class="overflow-hidden rounded-md border border-dashed border-white/20 bg-surface" aria-label="Cruce por definir">
                            @foreach ([1, 2] as $n)
                                <div class="flex items-center gap-2.5 px-3 py-2 {{ $n === 1 ? 'border-b border-white/[0.06]' : '' }}">
                                    <span class="size-6 shrink-0 rounded-[3px] bg-white/[0.06]" aria-hidden="true"></span>
                                    <span class="font-display text-base text-white/35">Por definir</span>
                                </div>
                            @endforeach
                        </div>
                    @endfor
                </div>
            </section>
        @endforeach

        {{-- El campeón, al final del cuadro, cuando la final está decidida. --}}
        @if ($champion)
            <section class="flex w-56 shrink-0 flex-col justify-center lg:w-auto lg:min-w-0 lg:flex-1">
                <div class="flex flex-col items-center gap-3 rounded-md bg-surface px-4 py-6 text-center {{ $intl ? 'border border-brand/40' : '' }}">
                    <span class="grid size-16 -skew-x-12 place-items-center rounded-[5px] bg-brand text-black/85" aria-hidden="true">
                        <svg viewBox="0 0 120 80" class="h-9 skew-x-12" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M42 8h36v18a18 18 0 0 1-36 0z" /><path d="M42 13H30v5a13 13 0 0 0 13 13M78 13h12v5a13 13 0 0 1-13 13" /><path d="M60 44v12M46 70h28M50 70l2-14h16l2 14" />
                        </svg>
                    </span>
                    <span class="font-mono text-[10px] font-bold tracking-[0.2em] text-brand">CAMPEÓN</span>
                    <x-site.team-crest :team="$champion" size="size-12" />
                    <span class="font-display text-2xl font-extrabold uppercase italic leading-tight text-white">{{ $champion->name }}</span>
                </div>
            </section>
        @endif
    </div>
</div>
