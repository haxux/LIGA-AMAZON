@props(['label', 'rows', 'kind' => 'goles', 'href' => null])

{{-- Una cabecera de la liga, como el bloque del líder de Estadísticas: la cara
     con su anillo, el nombre en cursiva, la cifra enorme y detrás la
     ilustración de su tema (la portería, el pase, el escudo del cero).

     Salen TODOS los que empaten arriba, no el primero por orden de id. Si tres
     van a cuatro goles, los tres son el máximo goleador. --}}
<section class="@container flex flex-col overflow-hidden rounded-md bg-surface">
    <h3 class="flex items-center justify-between gap-3 bg-surface-alt px-4 py-2.5">
        <span class="font-mono text-[10px] tracking-[0.12em] text-white/55">{{ mb_strtoupper($label) }} · LIGA</span>
        @if ($href)
            <a href="{{ $href }}" class="font-mono text-[10px] tracking-[0.12em] text-brand hover:text-white">TABLA</a>
        @endif
    </h3>

    @if ($rows->isEmpty())
        <p class="px-4 py-5 text-sm text-white/55">Sin datos todavía.</p>
    @else
        {{-- Las filas NO se estiran para rellenar la tarjeta: todas empiezan
             arriba y el hueco sobrante queda abajo, que se lee como espacio y
             no como descuadre. --}}
        <ul class="divide-y divide-white/[0.06]">
            @foreach ($rows as $row)
                <li>
                    <a href="{{ route('site.players.show', $row->player) }}"
                       class="relative flex items-center gap-3 overflow-hidden px-4 py-4 transition-colors hover:bg-surface-alt">
                        <x-site.stat-illustration :kind="$kind" class="absolute top-1/2 right-1 h-[80%] -translate-y-1/2" />

                        <x-site.player-avatar :player="$row->player" size="size-12" initials class="relative text-base ring-2 ring-brand" />

                        <span class="relative min-w-0 flex-1">
                            <span class="block truncate font-display text-lg font-extrabold uppercase italic leading-tight text-white">{{ $row->player->name }}</span>
                            <span class="block font-mono text-[10px] tracking-[0.06em] text-white/45">{{ $row->clubShortName }}</span>
                        </span>

                        <span class="relative -skew-x-8 font-display text-5xl leading-none font-extrabold tabular-nums text-brand [text-shadow:3px_3px_0_rgb(0_0_0/0.35)]">{{ $row->count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
