{{-- Una tabla de Estadísticas. El primero va aparte y en grande, que es lo que
     se viene a mirar; los demás, en filas compactas con un medidor de diez
     bloques que mide su cifra contra la del líder, como las barras de atributos
     de un juego de fútbol: así un 12 frente a un 6 se ve, no sólo se lee.

     Los puestos van del 1 al 10 por el orden de la lista, también con empates
     (decisión del propietario: es como se manejan en la liga). Los puestos 2
     y 3 llevan medalla de plata y bronce; el oro es el bloque del líder.

     Cada tabla tiene su color (`accent`), como los rótulos de una retransmisión,
     pero sólo en piezas macizas: la placa de la cabecera, la cifra del líder y
     los bloques del medidor. Dentro de la tabla no hay rellenos tintados.
     `panel` es la clave de la pestaña que la muestra en el móvil. --}}
@props(['title', 'rows', 'panel', 'accent' => '#FFB800', 'icon' => 'goles'])

@php
    $rows = collect($rows)->values();
    $max = max(1, (int) $rows->max('count'));

    $leader = $rows->first();

    $medals = [2 => 'bg-[#C9D1DB] text-ink', 3 => 'bg-[#CD8A4E] text-ink'];
@endphp

<section {{ $attributes->merge(['class' => '@container overflow-hidden rounded-md bg-surface']) }}
         style="--accent: {{ $accent }}" id="{{ $panel }}" data-stat-panel="{{ $panel }}">
    <h2 class="flex items-center gap-3 bg-surface-alt px-4 py-2.5">
        {{-- La placa de color con el icono, inclinada como los filtros. Va
             dentro de la cabecera y no pegada al borde: el amarillo del gol
             se fundía con el fondo de la página. --}}
        <span class="grid -skew-x-12 place-items-center rounded-[3px] bg-(--accent) px-2.5 py-1 text-ink"
              aria-hidden="true">
            <svg viewBox="0 0 24 24" class="size-5 skew-x-12" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                @switch($icon)
                    @case('asistencias')
                        <path d="M4 19c3-7 8-10 15-10" /><path d="M15 5l4 4-4 4" /><circle cx="5" cy="19" r="1.6" fill="currentColor" />
                        @break
                    @case('porterias')
                        <path d="M12 2.8l7 2.5V11c0 4.7-3 8.1-7 10-4-1.9-7-5.3-7-10V5.3z" /><ellipse cx="12" cy="11.5" rx="2.4" ry="3.4" />
                        @break
                    @case('mvp')
                        <path d="M12 3l2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8l-5.4 2.9 1.1-6.1-4.5-4.2 6.1-.8z" fill="currentColor" />
                        @break
                    @case('mvp-semana')
                        <path d="M7 4h10v4a5 5 0 0 1-10 0z" /><path d="M7 5H4v1.5A3.5 3.5 0 0 0 7.5 10" /><path d="M17 5h3v1.5A3.5 3.5 0 0 1 16.5 10" /><path d="M12 13v4" /><path d="M8 20.5h8" />
                        @break
                    @default
                        <circle cx="12" cy="12" r="9" /><path d="M12 8.2l3.2 2.3-1.2 3.8h-4l-1.2-3.8z" fill="currentColor" /><path d="M12 3v5.2M20.6 9.3l-5.4 1.2M17.3 19.4l-3.3-5.1M6.7 19.4l3.3-5.1M3.4 9.3l5.4 1.2" />
                @endswitch
            </svg>
        </span>
        <span class="font-display text-lg font-bold uppercase italic tracking-wide text-white">{{ $title }}</span>
    </h2>

    @if ($leader)
        <a href="{{ route('site.players.show', $leader->player) }}"
           class="relative flex items-center gap-4 overflow-hidden border-b border-white/[0.06] px-4 py-4 transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-(--accent)">
            {{-- Una ilustración grande del tema de la tabla, en línea blanca
                 tenue y entera: la portería con su red,
                 el pase, el escudo del cero, la estrella y el trofeo. En una
                 tarjeta ancha va a la izquierda de la cifra; en una angosta el
                 nombre ocupa ese sitio y la tapaba, así que va detrás de ella. --}}
            <svg class="pointer-events-none absolute top-1/2 right-1 h-[86%] -translate-y-1/2 text-white opacity-[0.1] @xl:right-20"
                 viewBox="0 0 120 80" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                @switch($icon)
                    @case('asistencias')
                        <path d="M18 62Q60 2 100 44" stroke-width="5" stroke-dasharray="10 8" />
                        <path d="M86 44l14 1-4-14" stroke-width="5" />
                        <circle cx="14" cy="64" r="10" stroke-width="4" />
                        <path d="M14 59l4.5 3.3-1.7 5.2h-5.6l-1.7-5.2z" fill="currentColor" stroke="none" />
                        @break
                    @case('porterias')
                        <path d="M60 6l26 9v20c0 17-11 29-26 37-15-8-26-20-26-37V15z" stroke-width="3.5" />
                        <ellipse cx="60" cy="38" rx="9" ry="13" stroke-width="5" />
                        @break
                    @case('mvp')
                        <path d="M60 4l10.5 21.5 23.5 3.4-17 16.6 4 23.5L60 58 39 69l4-23.5-17-16.6 23.5-3.4z" stroke-width="3.5" />
                        <path d="M100 10v10M95 15h10M20 56v8M16 60h8" stroke-width="3" />
                        @break
                    @case('mvp-semana')
                        <path d="M42 8h36v18a18 18 0 0 1-36 0z" stroke-width="3.5" />
                        <path d="M42 13H30v5a13 13 0 0 0 13 13M78 13h12v5a13 13 0 0 1-13 13" stroke-width="3" />
                        <path d="M60 44v12M46 70h28M50 70l2-14h16l2 14" stroke-width="3.5" />
                        @break
                    @default
                        {{-- La portería de frente, con su fondo y la red. --}}
                        <path d="M8 74V8h104v66" stroke-width="4" />
                        <path d="M8 8l12 12h80l12-12M20 20v54M100 20v54" stroke-width="2" />
                        <path d="M33 20v54M46 20v54M60 20v54M74 20v54M87 20v54M20 32h80M20 44h80M20 56h80M20 68h80" stroke-width="1" />
                        <path d="M2 74h116" stroke-width="2.5" />
                @endswitch
            </svg>
            <span class="relative shrink-0">
                <x-site.player-avatar :player="$leader->player" size="size-14" initials class="text-xl ring-2 ring-(--accent)" />
                <span class="absolute -right-1.5 -bottom-1 grid size-6 -skew-x-12 place-items-center rounded-[3px] bg-(--accent) font-display text-sm font-bold text-ink"
                      title="Puesto 1"><span class="skew-x-12">1</span></span>
            </span>
            <span class="relative min-w-0 flex-1">
                <span class="block truncate font-display text-2xl font-bold uppercase italic leading-tight text-white">{{ $leader->player->name }}</span>
                <span class="block font-mono text-[10px] tracking-[0.06em] text-white/55">{{ $leader->clubShortName }}</span>
            </span>
            <span class="relative -skew-x-8 font-display text-6xl font-extrabold leading-none tabular-nums text-(--accent) [text-shadow:3px_3px_0_rgb(0_0_0/0.35)]">{{ $leader->count }}</span>
        </a>

        <ol class="divide-y divide-white/[0.04]">
            @foreach ($rows->slice(1) as $index => $row)
                @php($rank = $index + 1)
                @php($filled = (int) max(1, round($row->count / $max * 10)))
                <li>
                    <a href="{{ route('site.players.show', $row->player) }}"
                       class="flex items-center gap-3 px-4 py-2 transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-(--accent)">
                        <span @class([
                                  'grid h-5 w-6 shrink-0 -skew-x-12 place-items-center rounded-[3px] font-display text-xs font-bold',
                                  $medals[$rank] ?? 'text-white/55',
                              ])><span class="skew-x-12">{{ $rank }}</span></span>
                        <x-site.player-avatar :player="$row->player" size="size-7" initials class="text-[11px]" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-display text-base font-semibold leading-tight text-white">{{ $row->player->name }}</span>
                            <span class="block truncate font-mono text-[10px] tracking-[0.06em] text-white/40">{{ $row->clubShortName }}</span>
                        </span>
                        {{-- El medidor: bloques llenos = la cifra sobre la del líder. --}}
                        <span class="flex shrink-0 gap-0.5" aria-hidden="true">
                            @for ($i = 1; $i <= 10; $i++)
                                <span @class(['h-3 w-1 -skew-x-12 rounded-[1px]', $i <= $filled ? 'bg-(--accent)' : 'bg-white/10'])></span>
                            @endfor
                        </span>
                        <span class="w-7 shrink-0 -skew-x-8 text-right font-display text-xl font-bold tabular-nums text-white">{{ $row->count }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    @else
        <p class="px-4 py-3 text-sm text-white/55">Sin datos todavía.</p>
    @endif
</section>
