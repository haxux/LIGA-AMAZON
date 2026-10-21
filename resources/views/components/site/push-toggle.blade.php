{{-- El estado de los avisos del navegador, dicho y con un botón para
     activarlos.

     Existe porque sin él esto no se puede diagnosticar: el permiso vive en el
     navegador, la suscripción en el servidor, y cuando falta una de las dos la
     página se queda callada. Un técnico no tiene manera de saber si le van a
     avisar o no, que es exactamente lo que pasó —el chat llevaba meses sin
     notificar a nadie y nada lo decía.

     El botón además es un gesto del usuario, que es la forma más fiable de que
     el navegador enseñe su diálogo de permiso: algunos lo ignoran cuando se
     pide al cargar la página. --}}
<div data-push-toggle class="hidden items-center gap-2 font-mono text-[10px] tracking-[0.1em] text-ink/60">
    <span data-push-state></span>
    <button type="button" data-push-enable
            class="hidden rounded-[4px] bg-ink px-3 py-1.5 font-display text-xs font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-surface">
        Activar avisos
    </button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var box = document.querySelector('[data-push-toggle]');
        var label = document.querySelector('[data-push-state]');
        var button = document.querySelector('[data-push-enable]');

        if (!box || typeof window.LigaPush === 'undefined') {
            return;
        }

        var vapidKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
        var csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!vapidKey) {
            return;
        }

        box.classList.remove('hidden');
        box.classList.add('flex');

        // En un iPhone sin instalar la web, Apple no da avisos: no hay botón
        // que sirva, sólo decir cómo conseguirlos.
        if (window.LigaPush.iosNeedsInstall()) {
            label.textContent = 'EN IPHONE, LOS AVISOS SÓLO LLEGAN CON LA WEB AÑADIDA A LA PANTALLA DE INICIO';
            button.classList.add('hidden');

            return;
        }

        if (!('Notification' in window)) {
            box.classList.add('hidden');

            return;
        }

        // El estado se lee de la suscripción real del navegador (isActive), no
        // de una marca guardada: la marca decía «activados» aunque el servidor
        // ya no tuviera la suscripción, y los avisos no llegaban.
        function paint() {
            window.LigaPush.isActive().then(function (active) {
                if (active) {
                    label.textContent = 'AVISOS DEL NAVEGADOR: ACTIVADOS';
                    button.classList.add('hidden');

                    return;
                }

                label.textContent = Notification.permission === 'denied'
                    ? 'AVISOS BLOQUEADOS EN ESTE NAVEGADOR'
                    : 'AVISOS DEL NAVEGADOR: DESACTIVADOS';

                // Bloqueado sólo se arregla desde los ajustes del navegador, así
                // que ofrecer un botón que no puede funcionar sería engañar.
                button.classList.toggle('hidden', Notification.permission === 'denied');
            });
        }

        button.addEventListener('click', function () {
            label.textContent = 'ACTIVANDO…';

            // A mano: se borra la marca de «ya se preguntó», que es justo lo
            // que impedía reintentarlo.
            window.localStorage.removeItem('liga-push-asked-chat');

            window.LigaPush.autoPrompt('chat', vapidKey, csrf).then(paint).catch(paint);
        });

        paint();
    });
</script>
