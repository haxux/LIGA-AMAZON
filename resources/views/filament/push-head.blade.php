{{-- Lo que el botón de abajo necesita: el token CSRF para el POST/DELETE a
     /push/subscribe y la clave pública VAPID para suscribir el navegador. --}}
<meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
<script src="{{ asset('js/push.js') }}" defer></script>
