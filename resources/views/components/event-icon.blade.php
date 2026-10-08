@props([
    'type',
    'secondYellow' => false,
    'size' => 'size-6',
])

@php
    use App\Models\GameEvent;

    // El `clipPath` del balón necesita un id propio por cada vez que se pinta:
    // en una ficha de partido hay varios goles, y repetir un id es HTML roto.
    $uid = \Illuminate\Support\Str::random(6);

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
    Estos iconos se pintan en DOS fondos: el oscuro del sitio y el claro del
    panel de Filament. Cada uno resuelve su contraste como le toca:

    - El balón lleva relleno claro y su contorno en `currentColor`, que lo cierra
      contra los dos fondos. La estela es blanca siempre, por decisión del
      propietario: luce sobre el fondo oscuro del sitio, que es donde se mira, y
      sobre el claro del panel no se ve.
    - El pase va en trazo con `currentColor`, que hereda el color del texto.
    - Las tarjetas llevan su color literal —amarillo y rojo— porque ahí el color
      ES el dato: una tarjeta gris no dice nada. Lo mismo el verde de la
      portería a cero.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center '.$size]) }}
      role="img" aria-label="{{ $nombre }}" title="{{ $nombre }}">
    @if ($type === GameEvent::TYPE_GOAL)
        {{-- El balón en vuelo con su estela, como la imagen que pidió el
             propietario: pelota grande arriba a la derecha y la cola de
             velocidad saliendo hacia abajo a la izquierda.

             Pentágonos MACIZOS y ninguna costura radial. Se probaron las
             costuras tres veces y a 24 px convierten el balón en una estrella:
             son líneas que salen del centro, y el ojo lee un asterisco antes
             que una pelota. Con los pentágonos recortados por el círculo se lee
             balón a 20 px.

             La pelota lleva relleno claro y trazo oscuro —se ve sobre los dos
             fondos, el oscuro del sitio y el claro del panel— y la estela va en
             `currentColor`, porque en negro desaparecería sobre el sitio. --}}
        <svg viewBox="0 0 24 24" class="size-full">
            {{-- La estela, blanca (decisión del propietario), del mismo blanco
                 que la pelota para que el icono sea una pieza. Sobre el fondo
                 claro del panel no se ve: ahí queda el balón solo, que sigue
                 leyéndose porque su contorno va en `currentColor`. --}}
            <g fill="#f8fafc">
                <path d="M7.6 14.6 C4.6 16.9 2.2 19.6 0.6 22.9 C4.3 21.3 7.6 19.1 10.9 16.4 Z" />
                <path d="M13.2 18.6 C11.2 19.9 9.6 21.2 8.2 23.2 C10.6 22.6 12.6 21.6 14.6 20.2 Z" />
            </g>

            <clipPath id="balon-{{ $uid }}">
                <circle cx="15" cy="9.4" r="8.4" />
            </clipPath>

            <circle cx="15" cy="9.4" r="8.4" fill="#f8fafc" />

            <g clip-path="url(#balon-{{ $uid }})" fill="#0f172a">
                <path d="M15 6.5 L17.76 8.5 L16.7 11.75 L13.3 11.75 L12.24 8.5 Z" />
                <path d="M18.29 4.87 L17.24 1.63 L20 -0.38 L22.75 1.63 L21.7 4.87 Z" />
                <path d="M20.33 11.13 L23.08 9.13 L25.84 11.13 L24.79 14.37 L21.38 14.37 Z" />
                <path d="M15 15 L17.76 17 L16.7 20.25 L13.3 20.25 L12.24 17 Z" />
                <path d="M9.67 11.13 L8.62 14.37 L5.21 14.37 L4.16 11.13 L6.92 9.13 Z" />
                <path d="M11.71 4.87 L8.3 4.87 L7.25 1.63 L10 -0.38 L12.76 1.63 Z" />
            </g>

            {{-- El contorno va ENCIMA del patrón y en `currentColor`. Los
                 pentágonos del borde son oscuros y tocan el canto: sobre el
                 fondo oscuro del sitio se fundían con él y mordían la silueta,
                 y el balón se leía como un engranaje. Cerrando el círculo por
                 arriba con el color del texto, la pelota vuelve a ser redonda
                 sobre cualquier fondo. --}}
            <circle cx="15" cy="9.4" r="8.4" fill="none" stroke="currentColor" stroke-width="1.5" />
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
