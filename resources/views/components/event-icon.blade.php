@props([
    'type',
    'secondYellow' => false,
    'size' => 'size-6',
])

@php
    use App\Models\GameEvent;

    // El nombre viaja con el icono: es el `aria-label` y el `title`, que es lo
    // que lee un lector de pantalla y lo que sale al pasar el ratón. Un icono
    // sin nombre obliga a adivinar, y aquí hay dos rojas que se parecen.
    $nombre = match (true) {
        $type === GameEvent::TYPE_YELLOW_CARD && $secondYellow => 'Doble amarilla: expulsado',
        $type === GameEvent::TYPE_GOAL => 'Gol',
        $type === GameEvent::TYPE_ASSIST => 'Asistencia',
        $type === GameEvent::TYPE_YELLOW_CARD => 'Tarjeta amarilla',
        $type === GameEvent::TYPE_RED_CARD => 'Tarjeta roja directa',
        $type === GameEvent::TYPE_CLEAN_SHEET => 'Portería a cero',
        default => (string) $type,
    };
@endphp

{{--
    El balón y el pase van en trazo con `currentColor`, no en relleno claro: este
    icono se pinta en el sitio, que es OSCURO, y en el panel, que es CLARO. Un
    balón blanco se evapora sobre el blanco del panel. Heredando el color del
    texto se ve en los dos.

    Las tarjetas sí llevan su color literal —amarillo y rojo— porque el color ES
    el dato: una tarjeta gris no dice nada. Lo mismo el verde de la portería a
    cero.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center '.$size]) }}
      role="img" aria-label="{{ $nombre }}" title="{{ $nombre }}">
    @if ($type === GameEvent::TYPE_GOAL)
        {{-- Balón de trazo: círculo, pentágono central y las cinco costuras que
             salen de sus vértices. A 24 px es lo único que se lee como balón —
             el relleno con pentágono oscuro salía un molinillo. --}}
        <svg viewBox="0 0 24 24" class="size-full" fill="none" stroke="currentColor"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9.2" stroke-width="1.7" />
            <path d="M12 8.4 L15.42 10.89 L14.12 14.91 L9.88 14.91 L8.58 10.89 Z" stroke-width="1.5" />
            <g stroke-width="1.4">
                <path d="M12 8.4 V3.1" />
                <path d="M15.42 10.89 L20.46 9.25" />
                <path d="M14.12 14.91 L17.23 19.2" />
                <path d="M9.88 14.91 L6.77 19.2" />
                <path d="M8.58 10.89 L3.54 9.25" />
            </g>
        </svg>
    @elseif ($type === GameEvent::TYPE_ASSIST)
        {{-- El pase: el balón que sale y la flecha de a dónde va. --}}
        <svg viewBox="0 0 24 24" class="size-full" fill="none" stroke="currentColor"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="6.8" cy="12" r="4.3" stroke-width="1.6" />
            <path d="M6.8 9.5 L8.9 11.05 L8.1 13.5 H5.5 L4.7 11.05 Z" stroke-width="1.2" />
            <path d="M13.4 12 H20.6 M17.6 9 L20.8 12 L17.6 15" stroke-width="1.9" />
        </svg>
    @elseif ($type === GameEvent::TYPE_YELLOW_CARD && $secondYellow)
        {{-- Doble amarilla: la amarilla detrás y la roja delante, que es como se
             dibuja una expulsión por acumulación en cualquier marcador. --}}
        <svg viewBox="0 0 24 24" class="size-full">
            <rect x="4" y="3.5" width="9" height="15" rx="1.6" fill="#facc15"
                  stroke="#854d0e" stroke-width="1" transform="rotate(-11 8.5 11)" />
            <rect x="11" y="5.5" width="9" height="15" rx="1.6" fill="#ef4444"
                  stroke="#7f1d1d" stroke-width="1" transform="rotate(11 15.5 13)" />
        </svg>
    @elseif ($type === GameEvent::TYPE_YELLOW_CARD)
        <svg viewBox="0 0 24 24" class="size-full">
            <rect x="7.5" y="3" width="9.5" height="16" rx="1.7" fill="#facc15"
                  stroke="#854d0e" stroke-width="1" transform="rotate(-8 12 11)" />
        </svg>
    @elseif ($type === GameEvent::TYPE_RED_CARD)
        <svg viewBox="0 0 24 24" class="size-full">
            <rect x="7.5" y="3" width="9.5" height="16" rx="1.7" fill="#ef4444"
                  stroke="#7f1d1d" stroke-width="1" transform="rotate(-8 12 11)" />
        </svg>
    @elseif ($type === GameEvent::TYPE_CLEAN_SHEET)
        {{-- Un escudo con un cero: lo que se guardó fue la portería. Sólo trazo,
             para que se vea igual sobre el fondo oscuro del sitio y sobre el
             claro del panel. --}}
        <svg viewBox="0 0 24 24" class="size-full" fill="none" stroke="#10b981"
             stroke-linejoin="round">
            <path d="M12 2.6 L19.2 5.2 V11 C19.2 15.9 16 19.4 12 21.4 C8 19.4 4.8 15.9 4.8 11 V5.2 Z"
                  stroke-width="1.6" />
            <ellipse cx="12" cy="11.6" rx="2.5" ry="3.3" stroke-width="1.7" />
        </svg>
    @else
        <svg viewBox="0 0 24 24" class="size-full" fill="none">
            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6" />
        </svg>
    @endif
</span>
