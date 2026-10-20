{{-- El título de una sección de la portada, sobre el fondo dorado y no dentro
     de una tarjeta, de ahí la tinta oscura. El enlace de la derecha es lo que
     hace que la portada sea un resumen y no un destino: cada bloque dice dónde
     está lo mismo completo. --}}
@props(['title', 'kicker' => null, 'href' => null, 'cta' => 'Ver todo'])

<div class="mt-12 mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h2 class="-skew-x-6 font-display text-3xl font-extrabold uppercase italic tracking-wide text-ink">{{ $title }}</h2>
        @if ($kicker)
            <div class="font-mono text-[10px] tracking-[0.14em] text-ink/50">{{ mb_strtoupper($kicker) }}</div>
        @endif
    </div>

    @if ($href)
        <a href="{{ $href }}"
           class="inline-flex shrink-0 items-center gap-1.5 font-display text-sm font-bold uppercase tracking-[0.08em] text-ink/70 transition-colors hover:text-ink">
            {{ $cta }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true">
                <path d="M9 6l6 6-6 6" />
            </svg>
        </a>
    @endif
</div>
