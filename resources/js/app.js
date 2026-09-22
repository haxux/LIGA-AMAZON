// La cabecera se recoge al bajar y vuelve a aparecer al subir, para no
// robarle sitio a la pantalla en un móvil mientras se lee un partido largo.
// Cerca del borde superior queda siempre visible, aunque se esté subiendo o
// bajando un pelín.
(function () {
    const header = document.getElementById('site-header');

    if (!header) {
        return;
    }

    const revealThreshold = header.offsetHeight;
    let lastScrollY = window.scrollY;

    window.addEventListener(
        'scroll',
        () => {
            const currentScrollY = window.scrollY;
            const scrollingDown = currentScrollY > lastScrollY;

            if (currentScrollY <= revealThreshold) {
                header.classList.remove('-translate-y-full');
            } else if (scrollingDown) {
                header.classList.add('-translate-y-full');
            } else {
                header.classList.remove('-translate-y-full');
            }

            lastScrollY = currentScrollY;
        },
        { passive: true },
    );
})();
