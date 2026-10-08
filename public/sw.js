// Service worker de las notificaciones push del sitio (partidos, recordatorios
// y chat). Sin caché de assets a propósito: lo único que hace falta aquí es
// recibir el push y abrir la página al pulsarlo — cachear páginas es un
// problema distinto que esta aplicación no tiene planteado.

self.addEventListener('push', function (event) {
    var data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = { title: 'Liga Amazon', body: event.data ? event.data.text() : '' };
    }

    var title = data.title || 'Liga Amazon';
    var options = {
        body: data.body || '',
        icon: '/favicon.png',
        badge: '/favicon.png',
        data: { url: data.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (var i = 0; i < windowClients.length; i++) {
                if (windowClients[i].url === url && 'focus' in windowClients[i]) {
                    return windowClients[i].focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        }),
    );
});
