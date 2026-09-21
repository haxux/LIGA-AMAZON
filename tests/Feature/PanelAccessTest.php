<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards the Filament panel's access policy (design D1, D7, D9).
 *
 * These go through the HTTP kernel rather than Livewire::test(), which every
 * other Filament test in this suite uses. Livewire::test() instantiates the
 * page component directly and never runs panel middleware, which is precisely
 * why a 403-in-production defect survived 169 passing tests.
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_reaches_the_panel_in_production(): void
    {
        // Filament's Authenticate middleware reads config('app.env') directly:
        // a user model that does not implement FilamentUser is denied in every
        // environment except 'local'.
        config()->set('app.env', 'production');

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_authenticated_user_still_reaches_the_panel_locally(): void
    {
        config()->set('app.env', 'local');

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    /**
     * canAccessPanel() granting access to every row in `users` is only sound
     * while `users` can be populated exclusively by `php artisan
     * make:filament-user`. If either guard below ever fails, the access policy
     * must become a real check before that path ships (design D1).
     */
    public function test_no_user_creation_path_exists_outside_administrator_action(): void
    {
        $this->assertFalse(
            Filament::getPanel('admin')->hasRegistration(),
            'The admin panel enabled registration. canAccessPanel() must be narrowed before this ships.',
        );

        $this->assertNull(
            Route::getRoutes()->getByName('register'),
            'A route named `register` appeared. canAccessPanel() must be narrowed before this ships.',
        );
    }

    /**
     * Desde que el chat, el once ideal y las fichas viven en el sitio, se cruza
     * de un lado a otro a menudo: cada panel deja la puerta a la vista.
     *
     * En dos tests y no en uno: visitar un panel deja a Filament con ése como
     * panel actual durante el resto del proceso, así que encadenar los dos aquí
     * pintaría el menú del primero en la segunda petición. En un navegador cada
     * petición es un proceso nuevo y eso no pasa.
     */
    public function test_the_admin_panel_links_back_to_the_public_site(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Go to the site')
            ->assertSee(route('site.standings'), false);
    }

    public function test_the_coach_panel_links_back_to_their_club_page(): void
    {
        $club = Club::factory()->create();

        $this->actingAs(User::factory()->coachOf($club)->create(), 'club')
            ->get('/club')
            ->assertOk()
            ->assertSee('Ir al sitio')
            ->assertSee(route('site.clubs.show', ['club' => $club->id]), false);
    }
}
