<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las dos puertas son independientes (corrección de la Fase 13, encontrada
 * probando en el navegador).
 *
 * Con un único guard `web` compartido por los dos paneles pasaban dos cosas, y
 * las dos se notaron: un administrador con la sesión abierta que iba a
 * `/club/login` era mandado al panel —`Login::mount()` da la sesión por buena,
 * porque es el mismo guard— y ahí `canAccessPanel` le devolvía un 403, así que
 * el TÉCNICO NUNCA VEÍA EL FORMULARIO por más que su contraseña fuese correcta;
 * y entrar como técnico echaba al administrador, porque una sesión sólo guarda
 * un usuario por guard.
 */
class TwoPanelSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function coach(): User
    {
        return User::factory()->coachOf(Club::factory()->create())->create();
    }

    /**
     * El fallo tal cual se vio: sesión de administrador abierta, y el técnico
     * intentando entrar en su panel desde otra pestaña.
     */
    public function test_an_open_admin_session_does_not_block_the_coachs_login_page(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->get('/club/login')->assertSuccessful();
    }

    public function test_an_open_coach_session_does_not_block_the_admin_login_page(): void
    {
        $this->actingAs($this->coach(), 'club');

        $this->get('/admin/login')->assertSuccessful();
    }

    /**
     * Las dos sesiones conviven: es lo que permite tener /admin en una pestaña
     * y /club en otra, con el mismo navegador.
     */
    public function test_both_panels_answer_at_once_to_their_own_user(): void
    {
        $admin = User::factory()->create();
        $coach = $this->coach();

        $this->actingAs($admin, 'web');
        $this->actingAs($coach, 'club');

        $this->get('/admin')->assertSuccessful();
        $this->get('/club')->assertSuccessful();
    }

    /**
     * Cada panel mira SU guard y ninguno hereda la sesión del otro: la puerta
     * sigue cerrada igual que antes.
     */
    public function test_an_admin_session_does_not_open_the_club_panel(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->get('/club')->assertRedirect('/club/login');
    }

    /**
     * En un test aparte y no como segunda mitad del anterior: `actingAs` deja
     * el guard resuelto en memoria y `flushSession()` no lo suelta, así que
     * encadenar los dos casos probaría una sesión que en un navegador no
     * existiría.
     */
    public function test_a_coach_session_does_not_open_the_admin_panel(): void
    {
        $this->actingAs($this->coach(), 'club');

        $this->get('/admin')->assertRedirect('/admin/login');
    }

    /**
     * `AuthenticateSession` vigila la sesión del guard por defecto, y en la pila
     * del panel corre antes de que Filament fije el suyo: sin ayuda, con las dos
     * puertas abiertas vigilaría la del administrador mientras atiende al
     * técnico. Que el hash guardado sea el del guard `club` es la prueba de que
     * mira a quien debe.
     */
    public function test_the_club_session_is_the_one_being_watched(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->actingAs($this->coach(), 'club');

        $this->get('/club')->assertSuccessful();

        $this->assertNotNull(session('password_hash_club'));
    }

    /**
     * Y dentro del panel, `auth()->user()` sigue siendo quien debe: Filament
     * hace `shouldUse()` del guard del panel al autenticar, así que el
     * acotamiento por club de cada recurso no cambia.
     */
    public function test_inside_the_club_panel_the_current_user_is_the_coach(): void
    {
        $admin = User::factory()->create();
        $coach = $this->coach();

        $this->actingAs($admin, 'web');
        $this->actingAs($coach, 'club');

        $this->get('/club/plantilla')->assertSuccessful();

        $this->assertTrue(auth()->guard('club')->user()->is($coach));
        $this->assertTrue(auth()->guard('web')->user()->is($admin));
    }
}
