@props(['entry', 'intl' => false])

{{-- Una ronda eliminatoria con sus cruces: la usan tanto las fases
     preliminares como el cuadro final. --}}
<section class="mb-8">
    <h3 class="font-display mb-3 rounded-[4px] bg-surface-alt px-4 py-2 text-lg uppercase tracking-wide text-brand">
        {{ $entry['round']->name }}
        <span class="font-mono text-[10px] tracking-[0.12em] text-white/40">
            · {{ $entry['round']->isTwoLegged() ? 'IDA Y VUELTA' : 'PARTIDO ÚNICO' }}
        </span>
    </h3>

    @forelse ($entry['ties'] as $item)
        @php
            $tie = $item['tie'];
            $result = $item['result'];
            $legs = $item['legs'];
            $sides = [
                ['team' => $tie->homeTeam, 'goals' => $result->homeGoals],
                ['team' => $tie->awayTeam, 'goals' => $result->awayGoals],
            ];
        @endphp

        {{-- Los nombres en los extremos y el global en el centro, con la ida
             y la vuelta al lado. Quién pasó no se escribe: su nombre brilla. --}}
        <div class="mb-3 rounded-[5px] bg-surface-alt px-4 py-4 sm:px-6 {{ $intl ? 'border-l-[3px] border-brand backdrop-blur-sm' : '' }}">
            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 sm:gap-10">
                @foreach ($sides as $index => $side)
                    @php($won = $result->isDecided() && $result->winner?->is($side['team']))
                    @php($lost = $result->isDecided() && ! $won)

                    @if ($index === 1)
                        {{-- El centro: el global grande y, a su lado, cada partido. --}}
                        <div class="flex flex-col items-center gap-2 sm:flex-row sm:gap-5">
                            <div class="text-center">
                                <div class="flex items-center gap-3 font-display text-4xl font-bold leading-none">
                                    <span class="{{ $result->winner?->is($tie->homeTeam) && $result->isDecided() ? 'text-brand' : 'text-white' }}">{{ $result->played === 0 ? '–' : $result->homeGoals }}</span>
                                    <span class="text-xl text-white/30">:</span>
                                    <span class="{{ $result->winner?->is($tie->awayTeam) && $result->isDecided() ? 'text-brand' : 'text-white' }}">{{ $result->played === 0 ? '–' : $result->awayGoals }}</span>
                                </div>
                                <div class="mt-1 font-mono text-[9px] tracking-[0.2em] text-white/40">{{ $legs->count() > 1 ? 'GLOBAL' : 'RESULTADO' }}</div>
                            </div>

                            @if ($legs->count() > 1)
                                <div class="flex gap-4 border-white/15 font-mono text-[10px] tracking-[0.08em] sm:flex-col sm:gap-1 sm:border-l sm:pl-5">
                                    @foreach ($legs as $leg)
                                        <a href="{{ route('site.games.show', $leg['game']) }}" class="flex items-center gap-2 text-white/55 hover:text-brand">
                                            <span class="w-12 text-white/35">{{ mb_strtoupper($leg['label']) }}</span>
                                            <span class="font-display text-sm font-semibold text-white/85">{{ $leg['home'] ?? '–' }} – {{ $leg['away'] ?? '–' }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <a href="{{ route('site.clubs.show', ['club' => $side['team']?->club_id]) }}"
                       class="flex min-w-0 items-center gap-2.5 {{ $index === 1 ? 'flex-row-reverse text-right' : '' }}">
                        @if ($side['team'])
                            <x-site.team-crest :team="$side['team']" size="size-[26px]" />
                        @endif
                        <span class="truncate font-display text-lg sm:text-xl {{ $won ? 'tie-winner font-bold' : ($lost ? 'text-white/45' : 'text-white') }}">
                            {{ $side['team']?->name }}
                        </span>
                    </a>
                @endforeach
            </div>

            {{-- Por qué pasó, si pasó por una decisión (penaltis, sorteo…): es
                 lo que nadie recuerda después. --}}
            @if ($result->isDecided() && $result->note)
                <div class="mt-2 text-center font-mono text-[10px] tracking-[0.1em] text-white/50">{{ $result->note }}</div>
            @elseif ($result->needsDecision())
                <div class="mt-2 text-center font-mono text-[10px] tracking-[0.1em] text-amber-400">EMPATE — PENDIENTE DE RESOLVER</div>
            @endif
        </div>
    @empty
        <p class="font-mono text-[11px] tracking-[0.08em] text-ink/50">Todavía sin emparejamientos.</p>
    @endforelse
</section>
