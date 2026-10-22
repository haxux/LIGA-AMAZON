{{-- La animación de entrada de la app instalada (decisión del propietario,
     2026-10-22): el escudo entra, una franja amarilla barre la pantalla,
     aparece la marca y todo sube para dejar ver la portada, que ya cargó por
     detrás. Unos 2,5 segundos.

     Sólo con la web abierta como app (la instalada en la pantalla de inicio),
     y sólo la primera vez en cada apertura: al navegar dentro de la app no se
     repite (`sessionStorage`). Se salta tocando la pantalla, y quien pide
     reducir el movimiento se la ahorra.

     Va lo primero del <body> y con su script en línea, para que se vea antes
     que la página y no al revés. `?intro=1` la fuerza en el navegador, para
     poder revisarla sin instalar nada. --}}
@props(['season' => null])

<div id="app-intro" class="app-intro" hidden aria-hidden="true">
    <div class="app-intro__stripe"></div>
    <div class="app-intro__body">
        <img src="{{ asset('icons/icon-512.png') }}" alt="" class="app-intro__crest" width="144" height="144">
        <x-site.wordmark size="text-3xl" class="app-intro__mark" />
        @if ($season)
            <span class="app-intro__season">{{ mb_strtoupper($season) }}</span>
        @endif
    </div>
</div>

<script>
    (function () {
        var intro = document.getElementById('app-intro');
        var standalone = window.navigator.standalone === true
            || window.matchMedia('(display-mode: standalone)').matches;
        var forced = /[?&]intro=1\b/.test(window.location.search);
        var seen = false;

        try { seen = window.sessionStorage.getItem('liga-intro') === '1'; } catch (error) {}

        if (!intro || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        if (!forced && (!standalone || seen)) {
            return;
        }

        try { window.sessionStorage.setItem('liga-intro', '1'); } catch (error) {}

        intro.hidden = false;
        document.documentElement.style.overflow = 'hidden';

        var done = false;

        function finish() {
            if (done) {
                return;
            }

            done = true;
            intro.classList.add('app-intro--out');

            setTimeout(function () {
                intro.remove();
                document.documentElement.style.overflow = '';
            }, 450);
        }

        intro.addEventListener('click', finish);
        setTimeout(finish, 2100);
    })();
</script>
