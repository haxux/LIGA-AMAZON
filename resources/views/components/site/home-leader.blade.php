@props(['label', 'rows'])

{{-- Una cabecera de la liga, con la cara grande: es lo que hace que la portada
     se sienta de gente y no de filas.

     Salen TODOS los que empaten arriba, no el primero por orden de id. Si tres
     van a cuatro goles, los tres son el máximo goleador. --}}
<section class="flex flex-col overflow-hidden rounded-md bg-surface">
    <h3 class="border-b border-white/[0.055] px-4 py-3 font-mono text-[10px] tracking-[0.12em] text-white/45">
        {{ mb_strtoupper($label) }} · LIGA
    </h3>

    @if ($rows->isEmpty())
        <p class="px-4 py-5 text-sm text-white/55">Sin datos todavía.</p>
    @else
        {{-- Las filas NO se estiran para rellenar la tarjeta: con una sola, un
             `flex-1` la centraba en el hueco y las tres tarjetas colocaban a su
             jugador a alturas distintas. Todas empiezan arriba y el hueco
             sobrante queda abajo, que se lee como espacio y no como descuadre. --}}
        <ul class="divide-y divide-white/[0.055]">
            @foreach ($rows as $row)
                <li>
                    <a href="{{ route('site.players.show', $row->player) }}"
                       class="flex items-center gap-3 px-4 py-4 hover:bg-surface-alt">
                        <x-site.player-avatar :player="$row->player" size="size-12" />

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-display text-lg font-semibold text-white">{{ $row->player->name }}</span>
                            <span class="block font-mono text-[10px] tracking-[0.06em] text-white/40">{{ $row->clubShortName }}</span>
                        </span>

                        <span class="font-display text-[30px] leading-none font-bold text-brand">{{ $row->count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
