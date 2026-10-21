<?php

/*
 * La identidad pública de la liga: lo que se ve al instalar la web como app
 * (el nombre bajo el icono, la barra del sistema) y lo que usa el manifest.
 *
 * La liga se renombrará (decisión del propietario, aplazada). Ese día se
 * cambia aquí, el componente `site.wordmark` (la marca de la cabecera y el
 * pie) y los iconos de `public/icons`, que hoy son el escudo actual.
 */
return [
    'name' => 'Amazon Toernooien',

    // Lo que cabe bajo el icono en la pantalla de inicio: unos doce caracteres.
    'short_name' => 'Toernooien',

    'description' => 'La liga de un vistazo: clasificación, partidos, equipos y noticias.',

    // El amarillo de la marca (`--color-brand`) y el grafito de la cabecera
    // (`--color-ink`), los mismos de resources/css/app.css.
    'theme_color' => '#1B1B1B',
    'background_color' => '#FFB800',
];
