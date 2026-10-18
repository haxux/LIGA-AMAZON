<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Activar/desactivar notificaciones push desde el navegador.
 *
 * "Partidos" no pide sesión: cualquier visitante lo activa desde el sitio
 * público. "Chat" sí — es el mismo criterio que `ChatController`, técnico o
 * presidente, el que esté abierto — porque sólo ellos tienen hilos que leer.
 */
class PushSubscriptionController extends Controller
{
    /**
     * Los servicios push de los navegadores: Chrome, Edge, Opera y Android van
     * por Google; Firefox, por Mozilla; Edge en Windows, por Microsoft; Safari,
     * por Apple. El endpoint lo da el navegador (`pushManager.subscribe()`), así
     * que uno de verdad siempre cuelga de alguno de éstos.
     *
     * @var list<string>
     */
    private const PUSH_SERVICES = [
        'fcm.googleapis.com',
        'android.googleapis.com',
        'push.services.mozilla.com',
        'notify.windows.com',
        'push.apple.com',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Sólo un servicio push de verdad, por https. El servidor le hace
            // un POST a esta dirección cada vez que avisa de algo, y la manda
            // un visitante anónimo: sin esto, cualquiera apuntaba el servidor
            // a una dirección interna (127.0.0.1, la red de Hostinger) o
            // ajena, y lo usaba para golpearla en cada partido terminado.
            'endpoint' => ['required', 'string', 'max:500', 'url:https', $this->pushServiceRule()],
            // Una clave P-256 en base64url son 87 caracteres, y el secreto,
            // 22. El tope deja margen y no deja meter megas.
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'topic' => ['required', Rule::in([PushSubscription::TOPIC_MATCHES, PushSubscription::TOPIC_NEWS, PushSubscription::TOPIC_CHAT])],
        ]);

        $user = auth('club')->user() ?? auth()->user();

        if ($data['topic'] === PushSubscription::TOPIC_CHAT && $user === null) {
            return response()->json(['message' => 'Hay que iniciar sesión para activar las notificaciones de chat.'], 401);
        }

        $subscription = PushSubscription::query()->firstOrNew(['endpoint' => $data['endpoint']]);

        $topics = array_unique([...($subscription->topics ?? []), $data['topic']]);

        $subscription->fill([
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'topics' => $topics,
            'user_id' => $data['topic'] === PushSubscription::TOPIC_CHAT ? $user->getKey() : $subscription->user_id,
        ])->save();

        return response()->json(['status' => 'subscribed', 'topics' => $topics]);
    }

    private function pushServiceRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));

            $known = collect(self::PUSH_SERVICES)->contains(
                fn (string $service): bool => $host === $service || str_ends_with($host, '.'.$service),
            );

            if (! $known) {
                $fail('Ese no es un servicio de notificaciones de un navegador.');
            }
        };
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'topic' => ['required', Rule::in([PushSubscription::TOPIC_MATCHES, PushSubscription::TOPIC_NEWS, PushSubscription::TOPIC_CHAT])],
        ]);

        $subscription = PushSubscription::query()->where('endpoint', $data['endpoint'])->first();

        if ($subscription === null) {
            return response()->json(['status' => 'not_subscribed']);
        }

        $topics = array_values(array_diff($subscription->topics ?? [], [$data['topic']]));

        if ($topics === []) {
            $subscription->delete();
        } else {
            $subscription->update(['topics' => $topics]);
        }

        return response()->json(['status' => 'unsubscribed']);
    }
}
