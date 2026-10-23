<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\PushSubscription;
use App\Models\Season;
use App\Models\User;
use App\Support\SiteAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El selector de cuentas (decisión del propietario, 2026-10-23): quien tiene
 * a la vez la cuenta de técnico y la de administrador abiertas pasa de una a
 * otra sin volver a escribir la contraseña, y el sitio funciona con la activa.
 */
class AccountSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Season::factory()->create(['is_current' => true]);
        $this->coach = User::factory()->coachOf(Club::factory()->create(['name' => 'SV Apoera']))->create(['name' => 'Josue Puello']);
        $this->admin = User::factory()->create(['name' => 'Presidente Puello']);
    }

    private function both(): static
    {
        return $this->actingAs($this->coach, 'club')->actingAs($this->admin, 'web');
    }

    public function test_with_both_open_the_coach_is_used_until_one_switches(): void
    {
        $this->both()->get(route('site.standings'))
            ->assertOk()
            ->assertSee('id="coach-club"', false)
            ->assertSee('Mi equipo')
            // Las dos cuentas, en el selector.
            ->assertSee('Josue Puello')
            ->assertSee('Presidente Puello');

        $this->post(route('site.account.switch'), ['guard' => 'web'])->assertRedirect();

        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('id="admin-panel"', false)
            ->assertDontSee('Mi equipo');

        $this->assertTrue(app(SiteAccount::class)->active()->is($this->admin));
    }

    public function test_one_cannot_switch_to_an_account_that_is_not_open(): void
    {
        $this->actingAs($this->coach, 'club')
            ->post(route('site.account.switch'), ['guard' => 'web'])
            ->assertRedirect();

        $this->assertTrue(app(SiteAccount::class)->active()->is($this->coach));
    }

    public function test_logging_out_leaves_the_other_account_open_and_in_use(): void
    {
        $this->both()->post(route('site.account.logout'))->assertRedirect(route('site.home'));

        $this->assertNull(auth('club')->user());
        $this->assertTrue(auth('web')->user()?->is($this->admin));
        $this->assertTrue(app(SiteAccount::class)->active()->is($this->admin));
    }

    public function test_the_account_just_logged_in_becomes_the_active_one(): void
    {
        $this->actingAs($this->coach, 'club');

        auth('web')->login($this->admin);

        $this->assertSame('web', app(SiteAccount::class)->activeGuard());
    }

    /**
     * Un teléfono con las dos cuentas recibe el chat de las dos; antes sólo el
     * de la última que se apuntaba.
     */
    public function test_one_browser_gets_the_chat_of_both_accounts(): void
    {
        $this->both()->postJson('/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/telefono',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
            'topic' => PushSubscription::TOPIC_CHAT,
        ])->assertOk();

        $subscription = PushSubscription::query()->sole();

        $this->assertEqualsCanonicalizing(
            [$this->coach->id, $this->admin->id],
            $subscription->users()->pluck('users.id')->all(),
        );
    }

    public function test_the_coach_login_offers_to_keep_the_session_open(): void
    {
        $this->get(route('filament.club.auth.login'))
            ->assertOk()
            ->assertSee('Mantener la sesión abierta en este dispositivo');
    }

    /** «Entrar con otra cuenta» lleva a su puerta y, al entrar, de vuelta aquí. */
    public function test_adding_an_account_goes_to_its_door_and_comes_back(): void
    {
        $this->actingAs($this->coach, 'club')
            ->from(route('site.fixtures'))
            ->get(route('site.account.add', 'web'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertSame(route('site.fixtures'), session(\App\Http\Controllers\Site\AccountSwitchController::RETURN_KEY));

        $this->get(route('site.account.add', 'otra'))->assertNotFound();
    }
}
