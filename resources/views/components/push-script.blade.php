{{-- El script de las notificaciones push, con la marca de su última
     modificación pegada a la URL.

     Sin ella no llega nunca un arreglo: estos estáticos se sirven con
     `cache-control: max-age=604800`, la URL no cambia, y el navegador —y la
     caché del servidor— siguen dando el de la semana pasada. Costó un
     despliegue entero el 2026-10-13: el fichero nuevo estaba en el servidor,
     6264 bytes, y la URL seguía devolviendo 4160.

     Lo cargan los dos sitios que lo necesitan —el sitio público y los dos
     paneles—, y de ahí que viva en un componente: con la expresión copiada,
     se arregla en uno y se olvida en el otro. --}}
<script src="{{ asset('js/push.js') }}?v={{ @filemtime(public_path('js/push.js')) ?: '0' }}" defer></script>
