<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * El rol llega en la Fase 9 y es lo que convierte el panel en dos: el
 * administrador entra en `/admin`, el director técnico en `/club`, y ninguno
 * pisa el del otro.
 */
class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_is_an_administrator_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertTrue($user->isAdmin());
        $this->assertNull($user->club_id);
    }

    public function test_a_coach_belongs_to_a_club(): void
    {
        $club = Club::factory()->create();
        $coach = User::factory()->coachOf($club)->create();

        $this->assertSame(User::ROLE_COACH, $coach->role);
        $this->assertTrue($coach->isCoach());
        $this->assertTrue($coach->club->is($club));
    }

    /**
     * Invariante de entidad: un técnico sin club no tiene nada que dirigir, y
     * cada pantalla de su panel se apoya en ese club. Se rechaza al guardar,
     * igual que los guards de `Game`, `Matchday` y `StandingZone`.
     */
    public function test_a_coach_without_a_club_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        User::create([
            'name' => 'Técnico sin club',
            'email' => 'tecnico@example.com',
            'password' => 'secret-password',
            'role' => User::ROLE_COACH,
        ]);
    }

    public function test_an_administrator_with_a_club_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        User::create([
            'name' => 'Admin con club',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'role' => User::ROLE_ADMIN,
            'club_id' => Club::factory()->create()->id,
        ]);
    }

    public function test_only_administrators_reach_the_admin_panel(): void
    {
        $coach = User::factory()->coachOf(Club::factory()->create())->create();

        $this->actingAs($coach)->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin')->assertSuccessful();
    }

    public function test_only_coaches_reach_the_club_panel(): void
    {
        $coach = User::factory()->coachOf(Club::factory()->create())->create();

        $this->actingAs($coach, 'club')->get('/club')->assertSuccessful();
        // Un administrador con sesión en el guard del técnico —que es lo único
        // que este panel mira— sigue sin pasar de la puerta.
        $this->actingAs(User::factory()->create(), 'club')->get('/club')->assertForbidden();
    }
}
