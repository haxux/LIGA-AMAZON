@props(['heading', 'rows'])

{{-- Los tres primeros de una división.

     El puesto va en una rampa de UN solo tono —el dorado de la marca a plena
     fuerza, luego al 30 %, luego al 15 %— y no en tres colores nuevos. El
     puesto es una categoría ordenada, que es justo el caso de la rampa
     ordinal; tres tonos inventados serían tres colores sin validar sobre este
     fondo, y además pintarían por rango en vez de por entidad. Así se lee el
     oro, la plata y el bronce sin salir del único acento del sitio. --}}
<section class="overflow-hidden rounded-md bg-surface">
    @if ($heading)
        <h3 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">{{ $heading }}</h3>
    @endif

    <ol class="divide-y divide-white/[0.055]">
        @foreach ($rows as $index => $row)
            @php($chip = [0 => 'bg-brand text-ink', 1 => 'bg-brand/30 text-brand'][$index] ?? 'bg-brand/15 text-brand')

            <li class="flex items-center gap-3 px-4 py-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full font-display text-sm font-bold {{ $chip }}">
                    {{ $index + 1 }}
                </span>

                <x-site.team-crest :team="$row->team" size="size-6" />

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-[19px] font-semibold tracking-[0.02em] text-white">
                        {{ $row->team->name }}
                    </span>
                    <span class="block font-mono text-[10px] tracking-[0.06em] text-white/40">
                        {{ $row->played }} PJ · {{ $row->goal_difference > 0 ? '+' : '' }}{{ $row->goal_difference }} DG
                    </span>
                </span>

                <span class="font-display text-[22px] font-bold text-brand">{{ $row->points }}</span>
            </li>
        @endforeach
    </ol>
</section>
