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

        // `updateViaCache: 'none'` para que el navegador pida SIEMPRE el
        // service worker al servidor en vez de servirlo de su caché HTTP.
        // Estos ficheros salen con `cache-control: max-age=604800`, y un
        // service worker viejo atascado una semana no hay quien lo note.
        return navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' });
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

            return postSubscription('POST', { endpoint: json.endpoint, keys: json.keys, topic: topic }, csrfToken).then(function () {
                remember(topic, json.endpoint);

                return true;
            });
        });
    }

    function supported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }

    // Cada cuánto se vuelve a avisar al servidor de una suscripción que ya
    // tiene. El alta es idempotente, y así una que el servidor borró por
    // caducada (ver PushNotificationService::deliver), o una que el navegador
    // renovó por su cuenta, vuelve sola en la siguiente visita en vez de
    // quedarse muda para siempre.
    var RESYNC_MS = 12 * 60 * 60 * 1000;

    // Qué cuentas había abiertas al apuntarse (una huella que pone el layout):
    // si cambian —se entra en otra o se sale de una—, hay que volver a decirle
    // al servidor a quién avisa del chat este navegador.
    function accounts() {
        var meta = document.querySelector('meta[name="push-accounts"]');

        return meta ? meta.content : '';
    }

    function remember(topic, endpoint) {
        window.localStorage.setItem('liga-push-' + topic, JSON.stringify({ endpoint: endpoint, at: Date.now(), who: accounts() }));
    }

    function remembered(topic) {
        try {
            return JSON.parse(window.localStorage.getItem('liga-push-' + topic) || 'null');
        } catch (error) {
            // La marca vieja era un '1' suelto: se trata como «sin marca».
            return null;
        }
    }

    /**
     * La suscripción del navegador, la de verdad, o null. Es la que manda: la
     * marca de `localStorage` sólo dice qué se mandó al servidor y cuándo.
     */
    function current() {
        if (!supported()) {
            return Promise.resolve(null);
        }

        return navigator.serviceWorker.getRegistration('/').then(function (registration) {
            return registration ? registration.pushManager.getSubscription() : null;
        });
    }

    /**
     * Deja asegurada la suscripción a un tema, preguntando sólo si hace falta.
     *
     * Antes esto era «preguntar una vez y nunca más», con la marca guardada en
     * `localStorage`. Y esa marca se consultaba AUNQUE no hubiera suscripción
     * ninguna, así que bastaba haber pasado una vez por un panel para que el
     * sitio no volviera a intentarlo jamás. Es lo que pasó: el permiso del chat
     * se pedía sólo dentro de los paneles, la marca quedó puesta, y después el
     * sitio ya no podía arreglarlo. Restablecer el permiso del navegador
     * tampoco servía, porque eso no borra `localStorage`.
     *
     * Ahora manda el permiso real del navegador, que es el único dato que no
     * miente:
     *
     *   concedido  → se suscribe en silencio, sin preguntar nada. Es lo que
     *                hace que esto se recupere solo: a quien ya dijo que sí
     *                por los partidos se le añade el chat sin molestarle.
     *   denegado   → nada; preguntar otra vez no está en nuestra mano.
     *   sin decidir→ se pregunta UNA vez, como antes.
     *
     * Con permiso concedido, además, ya no basta la marca de éxito: se mira la
     * suscripción real del navegador y se vuelve a dar de alta si cambió, si
     * no hay, o si hace más de RESYNC_MS que no se le dice al servidor. Hasta
     * el 2026-10-21 la marca cortaba aquí para siempre, y una suscripción que
     * el servidor había borrado nunca volvía: los avisos dejaban de llegar sin
     * ningún error. La marca de «ya se preguntó» sigue existiendo, pero sólo
     * gobierna la pregunta, nunca el alta.
     */
    function ensure(topic, vapidPublicKey, csrfToken) {
        if (!supported()) {
            return Promise.resolve(false);
        }

        if (Notification.permission === 'granted') {
            return current().then(function (subscription) {
                var mark = remembered(topic);
                var fresh = subscription && mark && mark.endpoint === subscription.endpoint
                    && mark.who === accounts() && Date.now() - mark.at < RESYNC_MS;

                return fresh ? true : subscribe(topic, vapidPublicKey, csrfToken);
            }).catch(function () {
                return false;
            });
        }

        if (Notification.permission === 'denied') {
            return Promise.resolve(false);
        }

        if (Notification.permission !== 'granted') {
            var askedKey = 'liga-push-asked-' + topic;

            if (window.localStorage.getItem(askedKey) === '1') {
                return Promise.resolve(false);
            }

            // Antes de intentarlo, para que un "no" tampoco vuelva a preguntar.
            window.localStorage.setItem(askedKey, '1');
        }

        return subscribe(topic, vapidPublicKey, csrfToken).catch(function () {
            // Denegado, sin soporte o sin sesión para el tema que lo pide: no
            // hay botón que informe del fallo, así que no queda más que hacer.
            return false;
        });
    }

    /**
     * Varios temas, UNO DETRÁS DE OTRO y no a la vez.
     *
     * En paralelo los dos acabarían pidiendo la misma suscripción del
     * navegador —el endpoint es uno por dispositivo— y mandando dos altas a la
     * vez por él, que es como se acaban creando dos filas para el mismo
     * aparato. En fila, la segunda se encuentra la del navegador ya hecha y
     * sólo añade su tema.
     */
    function autoPromptAll(topics, vapidPublicKey, csrfToken) {
        return topics.reduce(function (chain, topic) {
            return chain.then(function () {
                return ensure(topic, vapidPublicKey, csrfToken);
            });
        }, Promise.resolve());
    }

    /**
     * Si en este aparato los avisos llegan de verdad: permiso concedido y una
     * suscripción viva en el navegador. Es lo que debe decir el estado de la
     * página, no la marca de `localStorage`.
     */
    function isActive() {
        if (!supported() || Notification.permission !== 'granted') {
            return Promise.resolve(false);
        }

        return current().then(function (subscription) {
            return subscription !== null;
        }).catch(function () {
            return false;
        });
    }

    /**
     * Un iPhone o iPad con la web abierta en el navegador, sin instalar: ahí
     * Apple no da avisos web. Sólo con la web añadida a la pantalla de inicio
     * (iOS 16.4 o posterior).
     */
    function iosNeedsInstall() {
        var ios = /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var standalone = window.navigator.standalone === true
            || window.matchMedia('(display-mode: standalone)').matches;

        return ios && !standalone;
    }

    /**
     * Deja de recibir un tema en este navegador. Lo usa el layout con el chat
     * cuando ya no queda ninguna cuenta abierta: si no, el teléfono seguiría
     * avisando de los mensajes de quien cerró su sesión.
     */
    function release(topic, csrfToken) {
        if (!remembered(topic)) {
            return Promise.resolve(false);
        }

        return current().then(function (subscription) {
            window.localStorage.removeItem('liga-push-' + topic);

            if (!subscription) {
                return false;
            }

            return postSubscription('DELETE', { endpoint: subscription.endpoint, topic: topic }, csrfToken).then(function () {
                return true;
            });
        }).catch(function () {
            return false;
        });
    }

    window.LigaPush = {
        release: release,
        autoPrompt: ensure,
        autoPromptAll: autoPromptAll,
        isActive: isActive,
        iosNeedsInstall: iosNeedsInstall,
    };
})();
