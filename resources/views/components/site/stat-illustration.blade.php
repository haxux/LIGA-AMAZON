{{-- La ilustración del tema de una tabla de líderes, en línea blanca tenue:
     la portería con su red, el pase, el escudo del cero, la estrella y el
     trofeo. La usan las tablas de Estadísticas y los líderes de la portada;
     la posición y el tamaño los pone quien la usa, por `class`. --}}
@props(['kind' => 'goles'])

<svg {{ $attributes->merge(['class' => 'pointer-events-none text-white opacity-[0.1]']) }}
     viewBox="0 0 120 80" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($kind)
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
