<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * El envío de notificaciones push del sitio: partido terminado, recordatorio
 * de calendario y mensaje de chat. Las tres comparten el mismo mecanismo —Web
 * Push, sin servidor propio de mensajería— y por eso viven en un solo sitio.
 *
 * No hay cola ni worker en producción (ver `.env.production.example`,
 * `QUEUE_CONNECTION=sync`): cada envío ocurre en la misma petición que lo
 * dispara, uno por suscripción. Para una liga de doce clubes el volumen es
 * bajo y no justifica la cola que el despliegue no tiene.
 */
class PushNotificationService
{
    private ?WebPush $client = null;

    public function gameEnded(Game $game): void
    {
        $game->loadMissing(['homeTeam.club', 'awayTeam.club']);

        $home = $game->homeTeam?->name ?? 'Local';
        $away = $game->awayTeam?->name ?? 'Visitante';

        $this->sendToTopic(
            PushSubscription::TOPIC_MATCHES,
            'Partido terminado',
            "{$home} {$game->home_score} - {$game->away_score} {$away}",
            route('site.games.show', $game),
        );
    }

    /**
     * @param  array{0: string, 1: string}  $window  ['days_3'|'days_2'|'days_1'|'kickoff', copy]
     */
    public function gameReminder(Game $game, string $title): void
    {
        $game->loadMissing(['homeTeam.club', 'awayTeam.club']);

        $home = $game->homeTeam?->name ?? 'Local';
        $away = $game->awayTeam?->name ?? 'Visitante';

        $this->sendToTopic(
            PushSubscription::TOPIC_MATCHES,
            $title,
            "{$home} vs {$away}",
            route('site.games.show', $game),
        );
    }

    /**
     * Al otro participante del hilo, no a quien lo escribió. Sin mensaje
     * propio para quien no tiene ninguna suscripción de chat guardada.
     */
    public function chatMessage(Message $message): void
    {
        $conversation = $message->conversation()->with('participants')->first();
        $sender = $message->author;
        $recipient = $conversation?->other($sender);

        if ($recipient === null) {
            return;
        }

        $this->sendToUser(
            $recipient,
            PushSubscription::TOPIC_CHAT,
            'Nuevo mensaje de '.($sender?->name ?? 'alguien'),
            Str::limit($message->body, 80),
            route('site.chat'),
        );
    }

    private function sendToTopic(string $topic, string $title, string $body, string $url): void
    {
        $subscriptions = PushSubscription::query()->get()->filter(fn (PushSubscription $sub) => $sub->hasTopic($topic));

        $this->broadcast($subscriptions, $title, $body, $url);
    }

    private function sendToUser(User $user, string $topic, string $title, string $body, string $url): void
    {
        $subscriptions = PushSubscription::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->filter(fn (PushSubscription $sub) => $sub->hasTopic($topic));

        $this->broadcast($subscriptions, $title, $body, $url);
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    private function broadcast($subscriptions, string $title, string $body, string $url): void
    {
        if ($subscriptions->isEmpty()) {
            return;
        }

        $client = $this->client();

        if ($client === null) {
            return;
        }

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

        foreach ($subscriptions as $subscription) {
            $client->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token],
                ]),
                $payload,
            );
        }

        foreach ($client->flush() as $report) {
            // Un endpoint caducado (410/404) significa que el navegador se
            // desinstaló la suscripción por su lado: limpiarlo aquí es lo
            // único que la evita mandarle al vacío indefinidamente.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();

                continue;
            }

            if (! $report->isSuccess()) {
                Log::warning('Push notification failed', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }

    private function client(): ?WebPush
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');
        $subject = config('services.webpush.subject');

        if (blank($publicKey) || blank($privateKey)) {
            return null;
        }

        try {
            $this->client = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Could not initialise the web push client', ['exception' => $exception->getMessage()]);

            return null;
        }

        return $this->client;
    }
}
