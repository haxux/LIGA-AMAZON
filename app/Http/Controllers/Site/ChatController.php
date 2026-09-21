<?php

namespace App\Http\Controllers\Site;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * El chat, en el sitio público.
 *
 * Es la única página del sitio que pide sesión: se entra como técnico o como
 * presidente, y quien no tenga ninguna de las dos va al acceso de técnicos, que
 * es la puerta que el sitio ya ofrecía.
 */
class ChatController extends SiteController
{
    public function __invoke(): View|RedirectResponse
    {
        $user = auth('club')->user() ?? auth()->user();

        if ($user === null) {
            return redirect()->route('filament.club.auth.login');
        }

        return view('site.chat', ['user' => $user]);
    }
}
