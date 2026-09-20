<x-layouts.site :title="'Partidos — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Partidos</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">FIXTURES</div>

    @php
        $labelClass = 'mb-1 block font-mono text-[10px] tracking-[0.12em] text-white/50';
        $selectClass = 'w-full rounded-[4px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white';
    @endphp

    {{-- Plain GET form: the filters are shareable URLs, and the page still
         works with JavaScript off (the submit button below). --}}
    <form method="GET" action="{{ route('site.fixtures') }}" class="mb-8 grid gap-4 rounded-md bg-surface p-4 sm:grid-cols-3">
        <label>
            <span class="{{ $labelClass }}">TEMPORADA</span>
            <select name="temporada" onchange="this.form.submit()" class="{{ $selectClass }}">
                @foreach ($seasons as $season)
                    <option value="{{ $season->id }}" @selected($season->is($selectedSeason))>{{ $season->name }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="{{ $labelClass }}">DIVISIÓN</span>
            <select name="division" onchange="this.form.submit()" class="{{ $selectClass }}">
                <option value="{{ $all }}" @selected($selectedDivision === null)>Todas</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected($selectedDivision?->is($division))>{{ $division->name }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="{{ $labelClass }}">JORNADA</span>
            <select name="jornada" onchange="this.form.submit()" class="{{ $selectClass }}">
                <option value="{{ $all }}" @selected($selectedNumber === null)>Todas</option>
                @foreach ($numbers as $number)
                    <option value="{{ $number }}" @selected($selectedNumber === $number)>Jornada {{ $number }}</option>
                @endforeach
            </select>
        </label>

        <noscript>
            <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
        </noscript>
    </form>

    @forelse ($groups as $group)
        <section class="mb-10">
            <h2 class="font-display mb-4 rounded-[4px] bg-surface-alt px-4 py-2 text-lg uppercase tracking-wide text-brand">
                {{ $group['division']->name }}
            </h2>

            @foreach ($group['matchdays'] as $matchday)
                <div class="mb-6">
                    <h3 class="mb-3 flex flex-wrap items-baseline gap-2 font-mono text-xs uppercase tracking-[0.1em] text-ink/70">
                        <span>Jornada {{ $matchday->number }}</span>
                        @if ($matchday->date)
                            <span class="text-ink/45">· {{ $matchday->date->format('d/m/Y') }}</span>
                        @endif
                    </h3>

                    @if ($matchday->games->isEmpty())
                        <p class="font-mono text-[11px] tracking-[0.08em] text-ink/50">Sin partidos programados.</p>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($matchday->games as $game)
                                <x-site.game-card :game="$game" :matchday-number="$matchday->number" />
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    @empty
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            No hay partidos para este filtro.
        </p>
    @endforelse
</x-layouts.site>
