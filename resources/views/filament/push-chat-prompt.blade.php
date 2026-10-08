{{-- Al entrar por primera vez a su panel —técnico en /club, presidente en
     /admin—, el navegador pregunta por su cuenta si puede avisar de
     mensajes nuevos (ver public/js/push.js). Sin botón, una sola vez. --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.LigaPush === 'undefined') {
            return;
        }

        var vapidKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
        var csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        if (vapidKey) {
            window.LigaPush.autoPrompt('chat', vapidKey, csrf);
        }
    });
</script>
