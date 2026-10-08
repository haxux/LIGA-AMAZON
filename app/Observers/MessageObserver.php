<?php

namespace App\Observers;

use App\Models\Message;
use App\Services\PushNotificationService;

/**
 * Cada mensaje nuevo avisa al otro participante del hilo, si activó
 * notificaciones de chat. Mismo criterio que `GameObserver`: el envío es un
 * efecto de negocio sobre otro servicio, no algo que `Message` tenga que
 * saber hacer por sí mismo.
 */
class MessageObserver
{
    public function __construct(private readonly PushNotificationService $push) {}

    public function created(Message $message): void
    {
        $this->push->chatMessage($message);
    }
}
