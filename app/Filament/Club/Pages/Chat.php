<?php

namespace App\Filament\Club\Pages;

use App\Filament\Concerns\ChatScreen;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * El chat del técnico. Toda la lógica vive en el trait, que comparte con la
 * pantalla gemela de `/admin`: es el mismo hilo visto desde los dos lados.
 */
class Chat extends Page
{
    use ChatScreen;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Chat';

    protected static ?string $slug = 'chat';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.chat';

    public function getTitle(): string
    {
        return 'Chat';
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = static::unreadCount();

        return $unread === 0 ? null : (string) $unread;
    }
}
