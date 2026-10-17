<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\Club;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El ciclo de acceso, igual para las dos puertas: entrar, ir a donde se iba SI
 * es de esa puerta, salir sin tirar la otra, y los topes contra quien prueba
 * contraseñas. Cada caso es un enredo que se vio, o que se podía ver.
 */
class LoginLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'una-clave-larga-123';

    private User $admin;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email' => 'presi@liga.test', 'password' => self::PASSWORD]);
        $this->coach = User::factory()->coachOf(Club::factory()->create())->create(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD]);
    }

    /** Abre el formulario de acceso de una puerta. */
    private function door(string $panel): Testable
    {
        Filament::setCurrentPanel($panel);

        return Livewire::test(Login::class);
    }

    /**
     * Entra como en un navegador: dejando al usuario en la SESIÓN. `actingAs()`
     * sólo lo pone en memoria, y un cierre de sesión que vacía la sesión no
     * tendría nada que vaciar.
     */
    private function signIn(User $user, string $guard): void
    {
        session([auth($guard)->getName() => $user->getAuthIdentifier()]);
        session(['password_hash_'.$guard => $user->getAuthPassword()]);
    }

    private function signedIn(string $guard): bool
    {
        return session()->has(auth($guard)->getName());
    }

    // ── Entrar ────────────────────────────────────────────────────────────

    public function test_each_door_takes_its_own_user_to_its_own_panel(): void
    {
        $this->door('club')
            ->fillForm(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertRedirect(url('/club'));

        $this->assertTrue($this->signedIn('club'));
        $this->assertFalse($this->signedIn('web'));
    }

    public function test_the_admin_door_takes_the_administrator_to_admin(): void
    {
        $this->door('admin')
            ->fillForm(['email' => 'presi@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertRedirect(url('/admin'));

        $this->assertTrue($this->signedIn('web'));
        $this->assertFalse($this->signedIn('club'));
    }

    public function test_the_wrong_door_is_refused_and_leaves_no_session_behind(): void
    {
        $this->door('admin')
            ->fillForm(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertFalse($this->signedIn('web'));
        $this->assertFalse($this->signedIn('club'));

        $this->door('club')
            ->fillForm(['email' => 'presi@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertFalse($this->signedIn('web'));
        $this->assertFalse($this->signedIn('club'));
    }

    // ── A dónde se va después ─────────────────────────────────────────────

    /**
     * El enredo: quien tecleaba /admin dejaba esa URL pendiente en la sesión, y
     * al entrar luego por /club/login el éxito lo mandaba a /admin.
     */
    public function test_a_pending_url_of_the_other_door_is_not_followed(): void
    {
        session(['url.intended' => url('/admin/users')]);

        $this->door('club')
            ->fillForm(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertRedirect(url('/club'));
    }

    public function test_a_pending_url_of_the_same_door_is_followed(): void
    {
        session(['url.intended' => url('/club/plantilla')]);

        $this->door('club')
            ->fillForm(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertRedirect(url('/club/plantilla'));
    }

    public function test_a_pending_url_that_only_looks_like_the_panel_is_not_followed(): void
    {
        session(['url.intended' => url('/clubes-falsos')]);

        $this->door('club')
            ->fillForm(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertRedirect(url('/club'));
    }

    public function test_a_pending_url_of_another_site_is_not_followed(): void
    {
        session(['url.intended' => 'https://otro-sitio.test/club/plantilla']);

        $this->door('club')
            ->fillForm(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertRedirect(url('/club'));
    }

    // ── Salir ─────────────────────────────────────────────────────────────

    public function test_leaving_one_door_keeps_the_other_open(): void
    {
        $this->signIn($this->admin, 'web');
        $this->signIn($this->coach, 'club');

        $this->post(route('filament.club.auth.logout'))->assertRedirect();

        $this->assertFalse($this->signedIn('club'));
        $this->assertTrue($this->signedIn('web'), 'Salir del club no debe cerrar el panel del administrador.');
    }

    public function test_leaving_the_admin_door_keeps_the_coach_open(): void
    {
        $this->signIn($this->admin, 'web');
        $this->signIn($this->coach, 'club');

        $this->post(route('filament.admin.auth.logout'))->assertRedirect();

        $this->assertFalse($this->signedIn('web'));
        $this->assertTrue($this->signedIn('club'), 'Salir de /admin no debe cerrar la sesión del técnico.');
    }

    public function test_leaving_the_last_open_door_empties_the_session(): void
    {
        $this->signIn($this->coach, 'club');
        session(['algo' => 'que no debe sobrevivir']);

        $this->post(route('filament.club.auth.logout'))->assertRedirect();

        $this->assertFalse($this->signedIn('club'));
        $this->assertNull(session('algo'));
    }

    public function test_logout_changes_the_session_id_even_when_the_other_door_stays(): void
    {
        $this->signIn($this->admin, 'web');
        $this->signIn($this->coach, 'club');

        $before = session()->getId();

        $this->post(route('filament.club.auth.logout'));

        $this->assertNotSame($before, session()->getId());
    }

    // ── Topes y rastro ────────────────────────────────────────────────────

    public function test_ten_failures_lock_the_account_even_with_the_right_password(): void
    {
        $key = 'login-account:club:'.sha1('tecnico@liga.test');

        foreach (range(1, 10) as $_) {
            RateLimiter::hit($key, 900);
        }

        $this->door('club')
            ->fillForm(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate');

        $this->assertFalse($this->signedIn('club'));
    }

    public function test_the_account_lock_does_not_cross_to_the_other_door(): void
    {
        foreach (range(1, 10) as $_) {
            RateLimiter::hit('login-account:club:'.sha1('presi@liga.test'), 900);
        }

        $this->door('admin')
            ->fillForm(['email' => 'presi@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertRedirect(url('/admin'));
    }

    public function test_a_failure_counts_and_a_success_clears_the_counter(): void
    {
        $key = 'login-account:club:'.sha1('tecnico@liga.test');

        $this->door('club')
            ->fillForm(['email' => 'TECNICO@liga.test', 'password' => 'incorrecta'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertSame(1, RateLimiter::attempts($key), 'El correo se cuenta sin distinguir mayúsculas.');

        $this->door('club')
            ->fillForm(['email' => 'tecnico@liga.test', 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertRedirect(url('/club'));

        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_failures_leave_a_trace_without_the_password(): void
    {
        Log::spy();

        $this->door('admin')
            ->fillForm(['email' => 'presi@liga.test', 'password' => 'secreta-equivocada'])
            ->call('authenticate');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'login.failed'
                && $context['door'] === 'web'
                && $context['email'] === 'presi@liga.test'
                && ! str_contains(json_encode($context), 'secreta-equivocada'))
            ->once();
    }

    // ── Cuentas ───────────────────────────────────────────────────────────

    public function test_the_password_is_stored_hashed_never_in_clear(): void
    {
        $this->assertNotSame(self::PASSWORD, $this->coach->fresh()->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $this->coach->fresh()->password));
    }

    public function test_the_session_cookie_is_hardened_in_the_production_template(): void
    {
        $template = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $template);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $template);
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }
}
