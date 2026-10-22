{{-- Las noticias más recientes en un carrusel, lo primero de la portada
     (decisión del propietario, 2026-10-22): avanza solo cada pocos segundos y
     también se mueve a mano —con el dedo, las flechas o los puntos—.

     `slide` es el tamaño de cada noticia y `focus` el encuadre de su foto: los
     pone quien lo usa, porque en la portada ocupa media pantalla.

     Se apoya en el desplazamiento nativo con «snap»: sin JavaScript sigue
     siendo una tira que se desliza con el dedo, y el script sólo añade los
     controles y el avance automático. Éste se para al pasar el ratón por
     encima, al tocarlo o enfocarlo con el teclado, con la pestaña oculta, y
     no arranca solo si el teléfono pide reducir el movimiento: entonces queda
     el botón de reproducir. --}}
@props(['items', 'interval' => 6000, 'slide' => 'aspect-[4/3] lg:aspect-auto lg:h-[480px]', 'focus' => 'object-top'])

@php($count = $items->count())

<section {{ $attributes->merge(['class' => 'relative']) }}
         data-news-carousel data-interval="{{ $interval }}"
         aria-roledescription="carrusel" aria-label="Últimas noticias">
    <div data-track
         class="flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain rounded-md [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
         aria-live="off">
        @foreach ($items as $item)
            <div class="w-full shrink-0 snap-start"
                 role="group" aria-roledescription="noticia" aria-label="{{ $loop->iteration }} de {{ $count }}"
                 data-slide>
                <x-site.news-feature :item="$item" large :focus="$focus" :class="$slide" />
            </div>
        @endforeach
    </div>

    {{-- Los controles, que monta el script: sin él no harían nada. --}}
    <div data-controls class="mt-3 hidden items-center justify-between gap-3">
        <div class="flex items-center gap-1.5" aria-label="Elegir noticia">
            @foreach ($items as $item)
                <button type="button" data-dot="{{ $loop->index }}"
                        aria-label="Noticia {{ $loop->iteration }}: {{ $item->title }}"
                        class="h-2.5 w-6 -skew-x-12 rounded-[2px] bg-ink/25 transition-[width,background-color] duration-300 aria-[current=true]:w-10 aria-[current=true]:bg-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"></button>
            @endforeach
        </div>

        <div class="flex items-center gap-1.5">
            <button type="button" data-toggle
                    class="grid size-9 -skew-x-12 place-items-center rounded-[4px] bg-ink text-white transition-colors hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    aria-label="Pausar el paso automático">
                <svg data-icon-pause viewBox="0 0 24 24" class="size-4 skew-x-12" fill="currentColor" aria-hidden="true"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z" /></svg>
                <svg data-icon-play viewBox="0 0 24 24" class="hidden size-4 skew-x-12" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
            </button>
            <button type="button" data-prev
                    class="grid size-9 -skew-x-12 place-items-center rounded-[4px] bg-ink text-white transition-colors hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    aria-label="Noticia anterior">
                <svg viewBox="0 0 24 24" class="size-5 skew-x-12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6" /></svg>
            </button>
            <button type="button" data-next
                    class="grid size-9 -skew-x-12 place-items-center rounded-[4px] bg-ink text-white transition-colors hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    aria-label="Noticia siguiente">
                <svg viewBox="0 0 24 24" class="size-5 skew-x-12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
            </button>
        </div>
    </div>
</section>

@once
    <script>
        document.querySelectorAll('[data-news-carousel]').forEach(function (root) {
            var track = root.querySelector('[data-track]');
            var slides = root.querySelectorAll('[data-slide]');
            var dots = root.querySelectorAll('[data-dot]');
            var toggle = root.querySelector('[data-toggle]');
            var interval = parseInt(root.dataset.interval, 10) || 6000;

            if (slides.length < 2) {
                return;
            }

            root.querySelector('[data-controls]').classList.replace('hidden', 'flex');

            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var playing = !reduced;   // lo que eligió quien mira (el botón)
            var held = false;         // pausa momentánea: ratón, dedo, foco
            var timer = null;

            function index() {
                return Math.round(track.scrollLeft / track.clientWidth);
            }

            function go(i) {
                var n = (i + slides.length) % slides.length;
                track.scrollTo({ left: n * track.clientWidth, behavior: reduced ? 'auto' : 'smooth' });
            }

            function paint() {
                var current = index();
                dots.forEach(function (dot, i) {
                    dot.setAttribute('aria-current', String(i === current));
                });
                toggle.querySelector('[data-icon-pause]').classList.toggle('hidden', !playing);
                toggle.querySelector('[data-icon-play]').classList.toggle('hidden', playing);
                toggle.setAttribute('aria-label', playing ? 'Pausar el paso automático' : 'Reanudar el paso automático');
            }

            function schedule() {
                clearTimeout(timer);
                if (playing && !held && !document.hidden) {
                    timer = setTimeout(function () {
                        go(index() + 1);
                        schedule();
                    }, interval);
                }
            }

            dots.forEach(function (dot, i) {
                dot.addEventListener('click', function () { go(i); schedule(); });
            });
            root.querySelector('[data-prev]').addEventListener('click', function () { go(index() - 1); schedule(); });
            root.querySelector('[data-next]').addEventListener('click', function () { go(index() + 1); schedule(); });
            toggle.addEventListener('click', function () {
                playing = !playing;
                paint();
                schedule();
            });

            // Pausas momentáneas: mientras se mira o se toca, no se mueve.
            root.addEventListener('mouseenter', function () { held = true; schedule(); });
            root.addEventListener('mouseleave', function () { held = false; schedule(); });
            root.addEventListener('focusin', function () { held = true; schedule(); });
            root.addEventListener('focusout', function () { held = false; schedule(); });
            track.addEventListener('touchstart', function () { held = true; schedule(); }, { passive: true });
            track.addEventListener('touchend', function () {
                // Tras deslizar a mano, un respiro antes de seguir solo.
                setTimeout(function () { held = false; schedule(); }, interval);
            }, { passive: true });
            document.addEventListener('visibilitychange', schedule);

            var settle = null;
            track.addEventListener('scroll', function () {
                clearTimeout(settle);
                settle = setTimeout(paint, 80);
            }, { passive: true });

            paint();
            schedule();
        });
    </script>
@endonce
