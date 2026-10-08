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
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'topic' => ['required', Rule::in([PushSubscription::TOPIC_MATCHES, PushSubscription::TOPIC_CHAT])],
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

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'topic' => ['required', Rule::in([PushSubscription::TOPIC_MATCHES, PushSubscription::TOPIC_CHAT])],
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
