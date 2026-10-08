// Suscripción/desuscripción a notificaciones push del sitio. Un archivo
// propio, sin pasar por Vite: lo usan tanto el sitio público (tema
// "matches") como los dos paneles de Filament (tema "chat"), y un registro
// de servicio worker es exactamente el mismo código en los tres sitios.
(function () {
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);

        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    function getRegistration() {
        if (!('serviceWorker' in navigator)) {
            return Promise.resolve(null);
        }

        return navigator.serviceWorker.register('/sw.js');
    }

    function postSubscription(method, body, csrfToken) {
        return fetch('/push/subscribe', {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        }).then(function (response) {
            if (!response.ok) {
                return response.json().catch(function () {
                    return {};
                }).then(function (data) {
                    throw new Error(data.message || 'No se pudo completar la operación.');
                });
            }

            return response.json();
        });
    }

    function subscribe(topic, vapidPublicKey, csrfToken) {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return Promise.reject(new Error('Este navegador no admite notificaciones push.'));
        }

        return Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                throw new Error('Permiso de notificaciones denegado.');
            }

            return getRegistration().then(function (registration) {
                return registration.pushManager.getSubscription().then(function (existing) {
                    if (existing) {
                        return existing;
                    }

                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                    });
                });
            });
        }).then(function (subscription) {
            var json = subscription.toJSON();

            return postSubscription('POST', { endpoint: json.endpoint, keys: json.keys, topic: topic }, csrfToken);
        }).then(function () {
            window.localStorage.setItem('liga-push-' + topic, '1');

            return true;
        });
    }

    /**
     * Lo único que el sitio hace ahora con esto: preguntar una vez, al
     * primer visitante de cada tema, y ya. Sin botón ni forma de
     * desactivarlo desde aquí — quien no quiera avisos los bloquea desde los
     * ajustes de notificaciones del navegador, como con cualquier otro sitio.
     *
     * La marca de "ya se preguntó" se guarda ANTES de intentarlo, para que
     * una respuesta "no" tampoco vuelva a preguntar en la siguiente página.
     */
    function autoPrompt(topic, vapidPublicKey, csrfToken) {
        var askedKey = 'liga-push-asked-' + topic;

        if (window.localStorage.getItem(askedKey) === '1') {
            return;
        }

        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            return;
        }

        window.localStorage.setItem(askedKey, '1');

        subscribe(topic, vapidPublicKey, csrfToken).catch(function () {
            // Denegado o sin soporte: no hay botón que informe del fallo, así
            // que no queda nada más que hacer.
        });
    }

    window.LigaPush = { autoPrompt: autoPrompt };
})();
