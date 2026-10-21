{{-- Un filtro de pocas opciones, a la vista y a un toque, en lugar de un
     desplegable que las esconde. Cada opción es un enlace: la selección sigue
     siendo una URL que se comparte, y funciona sin JavaScript.

     `ruler` es la variante de las jornadas: números cortos en una tira que en
     el móvil se desliza de lado en vez de saltar a varias líneas. --}}
@props(['label', 'options', 'selected' => null, 'url', 'ruler' => false])

<div class="grid gap-1.5 sm:grid-cols-[7.5rem_minmax(0,1fr)] sm:items-center sm:gap-4">
    <span class="font-mono text-[10px] tracking-[0.12em] text-white/60">{{ $label }}</span>

    <div @class([
            'flex gap-1 rounded-[6px] bg-surface-alt px-2 py-1',
            'flex-wrap' => ! $ruler,
            'overflow-x-auto overscroll-x-contain [scrollbar-width:thin]' => $ruler,
         ])
         @if ($ruler) data-stat-ruler @endif>
        @foreach ($options as $value => $text)
            @php($active = (string) $value === (string) $selected)
            {{-- En la regla sólo los números van en corto; «Todas» (venga como
                 '' o como 'todas') va con su nombre entero. --}}
            @php($short = $ruler && is_numeric($value))
            <a href="{{ $url($value) }}"
               @if ($active) aria-current="true" @endif
               @class([
                   'shrink-0 -skew-x-12 rounded-[3px] font-display font-bold uppercase italic transition-colors',
                   'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand',
                   'px-3 py-1.5 text-sm tracking-[0.06em]' => ! $short,
                   'grid min-w-9 place-items-center py-1.5 text-base tabular-nums' => $short,
                   'bg-brand text-ink' => $active,
                   'text-white/60 hover:bg-white/[0.06] hover:text-white' => ! $active,
               ])>
                {{-- En la regla basta el número: «Jornada» ya lo dice la etiqueta. --}}
                <span class="inline-block skew-x-12">{{ $short ? $value : $text }}</span>
            </a>
        @endforeach
    </div>
</div>
