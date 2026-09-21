<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChatScreen;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * El chat del administrador — presidente, como le llaman los técnicos en el
 * chat. Misma pantalla que la del panel `/club`, con el mismo trait detrás.
 */
class Chat extends Page
{
    use ChatScreen;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Chat';

    protected static ?string $slug = 'chat';

    protected string $view = 'filament.chat';

    public static function getNavigationBadge(): ?string
    {
        $unread = static::unreadCount();

        return $unread === 0 ? null : (string) $unread;
    }
}
