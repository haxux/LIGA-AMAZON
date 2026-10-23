<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Message;
use App\Models\News;
use App\Models\Offer;
use App\Models\PushSubscription;
use App\Models\Transfer;
use App\Models\User;
use App\Models\WallPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

// El de Laravel, explícito: con Swoole instalado, el `defer()` global es el
// suyo y sólo funciona dentro de una corrutina.
use function Illuminate\Support\defer;

/**
 * El envío de notificaciones push del sitio: partido terminado, recordatorio
 * de calendario y mensaje de chat. Las tres comparten el mismo mecanismo —Web
 * Push, sin servidor propio de mensajería— y por eso viven en un solo sitio.
 *
 * No hay cola ni worker en producción (ver `.env.production.example`,
 * `QUEUE_CONNECTION=sync`): cada envío ocurre en la misma petición que lo
 * dispara —ya con la respuesta entregada, ver `broadcast()`—, uno por
 * suscripción. Para una liga de doce clubes el volumen es
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
     * Anuncia las noticias publicadas que todavía no se han anunciado, y las
     * marca.
     *
     * UN solo método para los DOS caminos que pueden dispararlo —guardar una
     * noticia ya publicada, y el barrido diario del cron para las programadas,
     * que se publican solas al llegar su fecha sin que nadie escriba nada—,
     * porque si cada uno decidiera por su cuenta a quién avisar acabarían
     * avisando distinto.
     *
     * La marca se pone con `saveQuietly()` a propósito: un `save()` normal
     * volvería a disparar el observador que llama aquí, y eso es una llamada
     * dentro de otra sin necesidad.
     *
     * @return int cuántas se anunciaron
     */
    public function publishedNews(): int
    {
        $pending = News::query()->awaitingNotification()->orderBy('published_at')->get();

        foreach ($pending as $item) {
            $this->sendToTopic(
                PushSubscription::TOPIC_NEWS,
                'Nueva noticia',
                $item->title,
                route('site.news.show', $item->slug),
            );

            $item->forceFill(['notified_at' => now()])->saveQuietly();
        }

        return $pending->count();
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

    /**
     * Una oferta que se mueve en el chat —nueva, contraoferta, aceptada o
     * rechazada— avisa al otro participante del hilo, por el mismo tema que
     * los mensajes (decisión del propietario, 2026-10-22). Quien la movió no
     * se avisa a sí mismo.
     *
     * @param  'sent'|'countered'|'accepted'|'rejected'  $event
     */
    public function offerMoved(Offer $offer, string $event): void
    {
        $notice = $this->offerNotice($offer, $event);

        if ($notice !== null) {
            $this->sendToUser($notice['to'], PushSubscription::TOPIC_CHAT, $notice['title'], $notice['body'], route('site.chat'));
        }
    }

    /**
     * Qué se avisa de una oferta y a quién, sin mandarlo: el otro participante
     * del hilo, un título con quién la movió y qué hizo, y la operación.
     *
     * @param  'sent'|'countered'|'accepted'|'rejected'  $event
     * @return array{to: User, title: string, body: string}|null
     */
    public function offerNotice(Offer $offer, string $event): ?array
    {
        // Una copia leída de nuevo, y no `loadMissing()` sobre la que llega:
        // cargarle relaciones a ESA instancia las dejaba fijadas para quien
        // la siga usando. Pasó con OfferService::accept, que leía el club del
        // jugador ya cargado aquí y no veía que se había ido a otro club.
        $offer = $offer->fresh(['conversation.participants', 'mover', 'player']);

        if ($offer === null) {
            return null;
        }

        $mover = $offer->mover;
        $recipient = $mover ? $offer->conversation?->other($mover) : null;

        if ($recipient === null) {
            return null;
        }

        $who = $mover->name;
        $terms = $offer->isFree()
            ? 'cesión '.(Transfer::LOAN_TERMS[$offer->loan_term] ?? 'sin plazo')
            : number_format((int) $offer->amount, 0, ',', '.');
        $what = (Offer::KINDS_SHORT[$offer->kind] ?? 'Oferta').' · '.$offer->playerName().' · '.$terms;

        $title = match ($event) {
            'sent' => 'Nueva oferta de '.$who,
            'countered' => 'Contraoferta de '.$who,
            'accepted' => $who.' aceptó la oferta',
            'rejected' => $who.' rechazó la oferta',
        };

        return ['to' => $recipient, 'title' => $title, 'body' => $what];
    }

    /**
     * Una respuesta en «A Segunda» avisa a quien escribió la publicación (no a
     * todos: avisar de cada publicación llenaría los teléfonos), por el mismo
     * tema que el chat, que es el de quien tiene sesión.
     */
    public function wallReply(WallPost $reply): void
    {
        $reply->loadMissing(['author', 'parent.author']);

        $author = $reply->parent?->author;

        if ($author === null || $author->is($reply->author)) {
            return;
        }

        $this->sendToUser(
            $author,
            PushSubscription::TOPIC_CHAT,
            ($reply->author?->name ?? 'Alguien').' respondió en A Segunda',
            Str::limit($reply->body ?? 'Una foto', 80),
            route('site.wall.show', $reply->parent_id),
        );
    }

    private function sendToTopic(string $topic, string $title, string $body, string $url): void
    {
        // El tema se filtra en la base y no trayendo todas las suscripciones
        // para mirarlas aquí: las de partidos y noticias son anónimas y no
        // tienen más tope que los visitantes que las activen.
        $subscriptions = PushSubscription::query()->whereJsonContains('topics', $topic)->get();

        $this->broadcast($subscriptions, $title, $body, $url);
    }

    private function sendToUser(User $user, string $topic, string $title, string $body, string $url): void
    {
        // Por la tabla de cuentas de cada navegador (ver la migración de
        // `push_subscription_user`): un teléfono puede avisar a varias.
        $subscriptions = PushSubscription::query()
            ->whereHas('users', fn ($users) => $users->whereKey($user->getKey()))
            ->whereJsonContains('topics', $topic)
            ->get();

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

        // La entrega, DESPUÉS de responder: hablar con los servicios push de
        // cada navegador son peticiones HTTP a terceros, y antes las esperaba
        // quien disparaba el aviso —el técnico al pulsar «Enviar» en el chat,
        // el administrador al guardar un resultado—. `defer()` lo corre al
        // terminar la petición (o el comando de consola) que lo pidió, y sólo
        // si ésta acabó bien. Lo de arriba sigue siendo síncrono a propósito:
        // qué se anuncia y a quién ya está decidido al volver de aquí.
        //
        // Lo que falle ahí ya no tiene a quién contestarle: se apunta y sigue,
        // para no llevarse por delante los demás avisos diferidos.
        defer(function () use ($client, $subscriptions, $payload): void {
            try {
                $this->deliver($client, $subscriptions, $payload);
            } catch (Throwable $exception) {
                Log::error('Push delivery failed', ['exception' => $exception->getMessage()]);
            }
        });
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    private function deliver(WebPush $client, Collection $subscriptions, string $payload): void
    {
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
