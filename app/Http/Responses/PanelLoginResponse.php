<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * A dónde va quien acaba de entrar: a lo que iba, SI es de esta puerta.
 *
 * Filament usa `redirect()->intended()`, y la URL pendiente vive en la sesión,
 * que es una sola para los dos paneles. Alguien que tecleaba `/admin` —o un
 * marcador viejo— y era mandado al acceso de administradores dejaba allí su
 * destino; si luego entraba por `/club/login`, el éxito lo llevaba a `/admin`,
 * que no es suyo: un 403 o el login otra vez, justo después de haber entrado
 * bien. Ahora el destino pendiente sólo vale si cuelga del panel por el que se
 * ha entrado y es de este mismo sitio; si no, va a la portada del panel.
 */
class PanelLoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $home = Filament::getUrl();
        $intended = session()->pull('url.intended');

        return redirect()->to($this->belongsToThisPanel($intended, $home) ? $intended : $home);
    }

    private function belongsToThisPanel(?string $url, string $home): bool
    {
        if (blank($url)) {
            return false;
        }

        $target = parse_url($url);
        $panel = parse_url($home);

        if (($target['host'] ?? null) !== ($panel['host'] ?? null)) {
            return false;
        }

        $base = rtrim($panel['path'] ?? '/', '/');
        $path = $target['path'] ?? '/';

        // En el límite de un segmento: `/club` vale para `/club/plantilla`,
        // pero `/clubes` no es de `/club`.
        return $path === $base || str_starts_with($path, $base.'/');
    }
}
