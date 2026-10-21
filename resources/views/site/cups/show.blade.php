@php
    $design = $cup->theme();
    $intl = $design !== null;
@endphp

{{-- Una copa internacional pasa su diseño al layout, que envuelve todo el
     contenido en su escenario (ver App\Enums\CupDesign). Una nacional se ve
     como siempre. --}}
<x-layouts.site :title="$cup->name.' — Liga Amazon'" :theme="$design">
    <div class="mb-4 font-mono text-[10px] tracking-[0.14em] text-ink/70">
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
                <img src="{{ $background }}" decoding="async" alt="{{ $cup->name }}" class="block aspect-[16/9] w-full object-cover sm:aspect-[16/7]">

                <div class="flex flex-wrap items-center justify-between gap-4 border-t-4 border-brand px-6 py-5">
                    <div class="flex min-w-0 items-center gap-4">
                        @if ($logo)
                            <img src="{{ $logo }}" decoding="async" alt="" class="h-14 w-auto shrink-0 object-contain">
                        @endif
                        <div class="min-w-0">
                            <div class="font-mono text-[10px] tracking-[0.3em] text-brand">{{ mb_strtoupper($design->label()) }}</div>
                            <h1 class="font-display truncate text-3xl font-extrabold uppercase tracking-wide text-white sm:text-4xl">{{ $cup->name }}</h1>
                        </div>
                    </div>
                    @if ($cup->season)
                        <div class="font-mono text-[11px] tracking-[0.25em] text-ink/70">{{ mb_strtoupper($cup->season->name) }}</div>
                    @endif
                </div>
            </header>
        @else
            <header class="mb-10 flex min-h-[20rem] flex-col items-center justify-center px-6 py-12 text-center sm:min-h-[24rem]">
                @if ($logo)
                    <img src="{{ $logo }}" decoding="async" alt="{{ $cup->name }}" class="mb-6 h-36 w-auto object-contain drop-shadow-[0_10px_30px_rgba(0,0,0,.55)] sm:h-48">
                @else
                    <span class="mb-4 font-display text-8xl text-brand drop-shadow-[0_10px_30px_rgba(0,0,0,.55)]" aria-hidden="true">&#9819;</span>
                @endif

                <div class="font-display text-sm font-semibold tracking-[0.35em] text-white/85">— {{ mb_strtoupper($design->label()) }} —</div>
                <h1 class="font-display mt-1 text-4xl font-extrabold uppercase tracking-wide text-white drop-shadow-[0_4px_14px_rgba(0,0,0,.6)] sm:text-6xl">{{ $cup->name }}</h1>
                @if ($cup->season)
                    <div class="mt-3 font-mono text-[11px] tracking-[0.25em] text-brand">{{ mb_strtoupper($cup->season->name) }}</div>
                @endif
            </header>
        @endif

    @else
        {{-- Una nacional, con una portada como la ficha de club: la placa de
             trofeo, el nombre en grande y la temporada. --}}
        <header class="mb-4 flex flex-wrap items-center gap-4 rounded-t-md bg-surface p-5 sm:gap-5 sm:p-6">
            <span class="grid size-16 shrink-0 -skew-x-12 place-items-center rounded-[5px] bg-brand text-black/85 sm:size-20" aria-hidden="true">
                <svg viewBox="0 0 120 80" class="h-9 skew-x-12 sm:h-11" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M42 8h36v18a18 18 0 0 1-36 0z" /><path d="M42 13H30v5a13 13 0 0 0 13 13M78 13h12v5a13 13 0 0 1-13 13" /><path d="M60 44v12M46 70h28M50 70l2-14h16l2 14" />
                </svg>
            </span>
            <div class="min-w-0">
                <h1 class="font-display -skew-x-6 text-3xl font-extrabold uppercase italic leading-none tracking-wide text-white sm:text-5xl">{{ $cup->name }}</h1>
                <p class="mt-2 font-mono text-[10px] tracking-[0.12em] text-white/60">
                    {{ $cup->has_group_stage ? 'FASE DE GRUPOS Y ELIMINATORIAS' : 'ELIMINATORIA DIRECTA' }}
                    @if ($cup->season) · {{ mb_strtoupper($cup->season->name) }} @endif
                </p>
            </div>
        </header>
    @endif

    {{-- Las cifras de la copa, de un vistazo, como el marcador de la ficha de
         club. En una nacional van pegadas a su portada. --}}
    <dl @class([
            'mb-12 grid grid-cols-3 divide-x divide-white/[0.06] overflow-hidden bg-surface-alt',
            'rounded-b-md' => ! $intl,
            'rounded-md border border-brand/25' => $intl,
            '-mt-4' => ! $intl,
        ])>
        @foreach ([
            ['value' => $cup->participants()->count(), 'label' => 'EQUIPOS'],
            ['value' => $groups->count() ?: '—', 'label' => 'GRUPOS'],
            ['value' => ($preliminaryRounds->count() + $rounds->count()) ?: '—', 'label' => 'RONDAS'],
        ] as $stat)
            <div class="px-4 py-3 sm:px-5">
                <dt class="font-mono text-[9px] tracking-[0.12em] text-white/60 sm:text-[10px]">{{ $stat['label'] }}</dt>
                <dd class="mt-0.5 -skew-x-8 font-display text-3xl font-extrabold leading-none tabular-nums text-brand sm:text-4xl">{{ $stat['value'] }}</dd>
            </div>
        @endforeach
    </dl>

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

        <div class="mb-10 grid gap-4">
            @foreach ($groups as $entry)
                <x-site.standings-table :heading="'Grupo '.$entry['group']->name" :rows="$entry['table']"
                                        :zones="$cup->standingZones" :legend="false" :spaced="false" />
            @endforeach
        </div>

        @if ($cup->standingZones->isNotEmpty())
            <div class="-mt-6 mb-10 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md bg-surface px-4 py-3 font-mono text-[10px] tracking-[0.06em] text-white/55">
                @foreach ($cup->standingZones as $zone)
                    <span class="flex items-center gap-1.5">
                        <span class="h-3 w-3.5 -skew-x-12 rounded-[2px]" style="background-color: {{ $zone->hex() }}"></span>
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

        <x-site.standings-table :heading="null" :rows="$bestThirds->pluck('row')" :zones="$cup->thirdsStandingZones"
                                :extra="['label' => 'GRUPO', 'values' => $bestThirds->map(fn ($entry) => mb_strtoupper($entry['group']->name))->all()]" />
    @endif

    {{-- El cuadro: las rondas después de los grupos. --}}
    <x-site.cup-heading :fancy="$intl">{{ $intl ? 'Fase final' : 'Cuadro' }}</x-site.cup-heading>

    @if ($rounds->isNotEmpty())
        <x-site.cup-bracket :rounds="$rounds" :intl="$intl" />
    @else
        <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
            Esta copa todavía no tiene rondas.
        </p>
    @endif
</x-layouts.site>
