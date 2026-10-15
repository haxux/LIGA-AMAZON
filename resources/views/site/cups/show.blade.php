@php
    $design = $cup->theme();
    $intl = $design !== null;
@endphp

{{-- Una copa internacional pasa su diseño al layout, que envuelve todo el
     contenido en su escenario (ver App\Enums\CupDesign). Una nacional se ve
     como siempre. --}}
<x-layouts.site :title="$cup->name.' — Liga Amazon'" :theme="$design">
    <div class="mb-4 font-mono text-[10px] tracking-[0.14em] text-ink/50">
        <a href="{{ route('site.cups.index', ['tipo' => $intl ? 'internacionales' : 'nacionales']) }}" class="hover:text-ink">
            {{ $intl ? 'COPAS INTERNACIONALES' : 'COPAS NACIONALES' }}
        </a>
        @if ($cup->season) · {{ mb_strtoupper($cup->season->name) }} @endif
    </div>

    @if ($intl)
        @php($logo = $cup->logoUrl())
        @php($background = $cup->backgroundUrl())

        {{-- El cartel. Si se subió una imagen de cabecera —el banner oficial,
             que ya trae su logo y su rótulo— se enseña entera y el nombre va
             debajo, para no escribir encima de su propio texto. Si no, el
             logo y el nombre van en el centro, sobre el escenario. --}}
        @if ($background)
            <header class="mb-10 overflow-hidden rounded-lg border border-brand/30 bg-surface-alt shadow-2xl">
                <img src="{{ $background }}" alt="{{ $cup->name }}" class="block aspect-[16/9] w-full object-cover sm:aspect-[16/7]">

                <div class="flex flex-wrap items-center justify-between gap-4 border-t-4 border-brand px-6 py-5">
                    <div class="flex min-w-0 items-center gap-4">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="" class="h-14 w-auto shrink-0 object-contain">
                        @endif
                        <div class="min-w-0">
                            <div class="font-mono text-[10px] tracking-[0.3em] text-brand">{{ mb_strtoupper($design->label()) }}</div>
                            <h1 class="font-display truncate text-3xl font-extrabold uppercase tracking-wide text-white sm:text-4xl">{{ $cup->name }}</h1>
                        </div>
                    </div>
                    @if ($cup->season)
                        <div class="font-mono text-[11px] tracking-[0.25em] text-ink/70">TEMPORADA {{ mb_strtoupper($cup->season->name) }}</div>
                    @endif
                </div>
            </header>
        @else
            <header class="mb-10 flex min-h-[20rem] flex-col items-center justify-center px-6 py-12 text-center sm:min-h-[24rem]">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $cup->name }}" class="mb-6 h-36 w-auto object-contain drop-shadow-[0_10px_30px_rgba(0,0,0,.55)] sm:h-48">
                @else
                    <span class="mb-4 font-display text-8xl text-brand drop-shadow-[0_10px_30px_rgba(0,0,0,.55)]" aria-hidden="true">&#9819;</span>
                @endif

                <div class="font-display text-sm font-semibold tracking-[0.35em] text-white/85">— {{ mb_strtoupper($design->label()) }} —</div>
                <h1 class="font-display mt-1 text-4xl font-extrabold uppercase tracking-wide text-white drop-shadow-[0_4px_14px_rgba(0,0,0,.6)] sm:text-6xl">{{ $cup->name }}</h1>
                @if ($cup->season)
                    <div class="mt-3 font-mono text-[11px] tracking-[0.25em] text-brand">TEMPORADA {{ mb_strtoupper($cup->season->name) }}</div>
                @endif
            </header>
        @endif

        {{-- Las cifras de la copa, de un vistazo. --}}
        <div class="mb-12 grid grid-cols-3 gap-3 text-center">
            @foreach ([
                ['value' => $cup->participants()->count(), 'label' => 'EQUIPOS'],
                ['value' => $groups->count() ?: '—', 'label' => 'GRUPOS'],
                ['value' => ($preliminaryRounds->count() + $rounds->count()) ?: '—', 'label' => 'RONDAS'],
            ] as $stat)
                <div class="rounded-md border border-brand/25 bg-surface px-3 py-4 backdrop-blur-sm">
                    <div class="font-display text-3xl font-bold text-brand sm:text-4xl">{{ $stat['value'] }}</div>
                    <div class="mt-1 font-mono text-[9px] tracking-[0.2em] text-ink/60">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    @else
        <h1 class="font-display mb-6 text-3xl uppercase tracking-wide text-ink">{{ $cup->name }}</h1>
    @endif

    {{-- Las fases preliminares (de clasificación): eliminatorias que se
         juegan antes de los grupos, para entrar en ellos. --}}
    @if ($preliminaryRounds->isNotEmpty())
        <x-site.cup-heading :fancy="$intl">Fases preliminares</x-site.cup-heading>

        <div class="mb-10">
            @foreach ($preliminaryRounds as $entry)
                <x-site.cup-round :entry="$entry" :intl="$intl" />
            @endforeach
        </div>
    @endif

    {{-- Los grupos, si los lleva: cada uno es una liga pequeña, y se clasifica
         con el mismo código que una división. --}}
    @if ($groups->isNotEmpty())
        <x-site.cup-heading :fancy="$intl">Fase de grupos</x-site.cup-heading>

        <div class="mb-10 grid gap-4 lg:grid-cols-2">
            @foreach ($groups as $entry)
                <section class="overflow-hidden rounded-md bg-surface {{ $intl ? 'border border-brand/25 backdrop-blur-sm' : '' }}">
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
                                @php($zone = $cup->standingZones->first(fn ($candidate) => $candidate->covers($index + 1)))
                                <tr class="font-display text-sm text-white">
                                    <td class="relative px-4 py-2 text-white/40">
                                        @if ($zone)
                                            {{-- Color de StandingZone::COLORS, una paleta cerrada. --}}
                                            <span class="absolute inset-y-0 left-0 w-[3px]" style="background-color: {{ $zone->hex() }}"></span>
                                        @endif
                                        {{ $index + 1 }}
                                    </td>
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

        @if ($cup->standingZones->isNotEmpty())
            <div class="-mt-6 mb-10 flex flex-wrap items-center gap-4 px-1 font-mono text-[10px] tracking-[0.06em] text-white/40">
                @foreach ($cup->standingZones as $zone)
                    <span class="flex items-center gap-1.5">
                        <span class="size-2 rounded-sm" style="background-color: {{ $zone->hex() }}"></span>
                        {{ mb_strtoupper($zone->label) }}
                    </span>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Los mejores terceros, sólo con 3 grupos o más: con menos serían todos
         los terceros que hay, no una criba. No es una tabla propia —es el
         tercero de cada grupo de arriba, puesto a competir con los demás—, así
         que no lleva PJ/G/E/P propios: ya están en su grupo. --}}
    @if ($bestThirds->isNotEmpty())
        <x-site.cup-heading :fancy="$intl">Mejores terceros</x-site.cup-heading>

        <section class="mb-10 overflow-hidden rounded-md bg-surface {{ $intl ? 'border border-brand/25 backdrop-blur-sm' : '' }}">
            <table class="w-full text-left">
                <thead>
                    <tr class="font-mono text-[9px] tracking-[0.12em] text-white/40">
                        <th class="px-4 py-2">#</th>
                        <th class="px-2 py-2">EQUIPO</th>
                        <th class="px-2 py-2">GRUPO</th>
                        @foreach (['PJ', 'G', 'E', 'P', 'DG', 'PTS'] as $head)
                            <th class="px-2 py-2 text-center">{{ $head }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.055]">
                    @foreach ($bestThirds as $index => $entry)
                        @php($row = $entry['row'])
                        @php($zone = $cup->thirdsStandingZones->first(fn ($candidate) => $candidate->covers($index + 1)))
                        <tr class="font-display text-sm text-white">
                            <td class="relative px-4 py-2 text-white/40">
                                @if ($zone)
                                    <span class="absolute inset-y-0 left-0 w-[3px]" style="background-color: {{ $zone->hex() }}"></span>
                                @endif
                                {{ $index + 1 }}
                            </td>
                            <td class="truncate px-2 py-2">
                                <a href="{{ route('site.clubs.show', ['club' => $row->team->club_id]) }}" class="hover:text-brand">
                                    {{ $row->team->name }}
                                </a>
                            </td>
                            <td class="px-2 py-2 font-mono text-[10px] tracking-[0.1em] text-white/45">
                                {{ mb_strtoupper($entry['group']->name) }}
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

            @if ($cup->thirdsStandingZones->isNotEmpty())
                <div class="flex flex-wrap items-center gap-4 px-4 py-3 font-mono text-[10px] tracking-[0.06em] text-white/40">
                    @foreach ($cup->thirdsStandingZones as $zone)
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-sm" style="background-color: {{ $zone->hex() }}"></span>
                            {{ mb_strtoupper($zone->label) }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- El cuadro: las rondas después de los grupos. --}}
    <x-site.cup-heading :fancy="$intl">{{ $intl ? 'Fase final' : 'Cuadro' }}</x-site.cup-heading>

    @forelse ($rounds as $entry)
        <x-site.cup-round :entry="$entry" :intl="$intl" />
    @empty
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            Esta copa todavía no tiene rondas.
        </p>
    @endforelse
</x-layouts.site>
