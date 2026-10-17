<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * El acceso de las dos puertas (administrador y técnico), con el mismo ciclo.
 *
 * Filament ya limita por IP (5 por minuto) y rechaza a quien no puede abrir el
 * panel sin dejarle sesión. Lo que añade esto:
 *
 *  - Un tope POR CUENTA, además del de IP: quien adivina una contraseña desde
 *    muchas direcciones esquiva el límite por IP, y la cuenta de un
 *    administrador es la que más vale. 10 fallos en 15 minutos la cierran un
 *    rato, vengan de donde vengan.
 *  - Una línea en el log por cada fallo y cada bloqueo, con la puerta, el
 *    correo y la IP —nunca la contraseña—, que es lo que hace falta para
 *    enterarse de que alguien está probando.
 *
 * Un acierto borra el contador de la cuenta: el tope es contra la fuerza bruta,
 * no contra quien se equivocó dos veces.
 */
class Login extends BaseLogin
{
    private const MAX_FAILURES = 10;

    private const WINDOW_SECONDS = 900;

    public function authenticate(): ?LoginResponse
    {
        $key = $this->accountKey();

        if ($key !== null && RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            Log::warning('login.locked', $this->context(['retry_in' => RateLimiter::availableIn($key)]));

            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                (string) request()->ip(),
                RateLimiter::availableIn($key),
            ))?->send();

            return null;
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            if ($key !== null) {
                RateLimiter::hit($key, self::WINDOW_SECONDS);
            }

            Log::warning('login.failed', $this->context());

            throw $exception;
        }

        if ($response !== null && $key !== null) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    /**
     * La clave del tope por cuenta: el correo (sin distinguir mayúsculas) y la
     * puerta, para que fallar en una no cierre la otra.
     */
    private function accountKey(): ?string
    {
        $email = mb_strtolower(trim((string) ($this->data['email'] ?? '')));

        return $email === '' ? null : 'login-account:'.Filament::getAuthGuard().':'.sha1($email);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(array $extra = []): array
    {
        return $extra + [
            'door' => Filament::getAuthGuard(),
            'email' => mb_strtolower(trim((string) ($this->data['email'] ?? ''))),
            'ip' => request()->ip(),
        ];
    }
}
