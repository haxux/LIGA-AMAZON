@props(['champions', 'scorers'])

{{-- El palmarés de una temporada cerrada: quién la ganó y quién marcó más.
     Las dos cifras son de la LIGA —el campeón sale de la tabla de liga y el
     goleador se pide con Competition::league()—, porque dos números juntos que
     vengan de torneos distintos se leen como si fueran del mismo. --}}
@if (count($champions) > 0 || $scorers->isNotEmpty())
    <section class="mb-10 overflow-hidden rounded-md bg-surface">
        <h2 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">Palmarés</h2>

        <div class="grid gap-px bg-white/[0.055] sm:grid-cols-2">
            @if (count($champions) > 0)
                <div class="bg-surface p-4">
                    <div class="mb-3 font-mono text-[10px] tracking-[0.12em] text-white/60">
                        {{ count($champions) > 1 ? 'CAMPEONES' : 'CAMPEÓN' }}
                    </div>

                    <ul class="flex flex-col gap-3">
                        @foreach ($champions as $champion)
                            <li class="flex items-center gap-3">
                                <x-site.team-crest :team="$champion['row']->team" size="size-8" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-display text-[19px] font-semibold tracking-[0.02em] text-white">
                                        {{ $champion['row']->team->name }}
                                    </span>
                                    @if ($champion['division'])
                                        <span class="block font-mono text-[10px] tracking-[0.06em] text-white/60">
                                            {{ mb_strtoupper($champion['division']) }}
                                        </span>
                                    @endif
                                </span>
                                <span class="font-display text-[22px] font-bold text-brand">{{ $champion['row']->points }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($scorers->isNotEmpty())
                <div class="bg-surface p-4">
                    {{-- Plural cuando empatan: elegir a uno de dos que marcaron
                         los mismos goles sería inventar un desempate. --}}
                    <div class="mb-3 font-mono text-[10px] tracking-[0.12em] text-white/60">
                        {{ $scorers->count() > 1 ? 'MÁXIMOS GOLEADORES' : 'MÁXIMO GOLEADOR' }} · LIGA
                    </div>

                    <ul class="flex flex-col gap-3">
                        @foreach ($scorers as $scorer)
                            <li>
                                <a href="{{ route('site.players.show', $scorer->player) }}"
                                   class="-m-1 flex items-center gap-3 rounded-[4px] p-1 hover:bg-surface-alt">
                                    <x-site.player-avatar :player="$scorer->player" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-display text-base font-semibold text-white">{{ $scorer->player->name }}</span>
                                        <span class="block font-mono text-[10px] tracking-[0.06em] text-white/60">{{ $scorer->clubShortName }}</span>
                                    </span>
                                    <span class="font-display text-[22px] font-bold text-brand">{{ $scorer->count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@endif
