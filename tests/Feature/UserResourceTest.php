<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las cuentas de técnico las crea el administrador: usuario, contraseña y el
 * club del que se encarga.
 */
class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListUsers::class)->assertOk();
    }

    public function test_admin_creates_a_coach_account_for_a_club(): void
    {
        $club = Club::factory()->create();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Jorge Técnico',
                'email' => 'jorge@example.com',
                'password' => 'una-contraseña-larga',
                'role' => User::ROLE_COACH,
                'club_id' => $club->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coach = User::where('email', 'jorge@example.com')->firstOrFail();

        $this->assertSame(User::ROLE_COACH, $coach->role);
        $this->assertSame($club->id, $coach->club_id);
        $this->assertTrue(Hash::check('una-contraseña-larga', $coach->password));
    }

    public function test_a_coach_without_a_club_is_rejected_as_a_form_error(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Sin club',
                'email' => 'sinclub@example.com',
                'password' => 'una-contraseña-larga',
                'role' => User::ROLE_COACH,
            ])
            ->call('create')
            ->assertHasFormErrors(['club_id']);

        $this->assertSame(0, User::where('email', 'sinclub@example.com')->count());
    }

    /**
     * Un club tiene un técnico y un técnico un club: la segunda cuenta para el
     * mismo club se rechaza en el formulario, no al guardar.
     */
    public function test_a_club_cannot_have_two_coaches(): void
    {
        $club = Club::factory()->create();
        User::factory()->coachOf($club)->create();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Segundo técnico',
                'email' => 'segundo@example.com',
                'password' => 'una-contraseña-larga',
                'role' => User::ROLE_COACH,
                'club_id' => $club->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['club_id']);

        $this->assertSame(1, User::where('club_id', $club->id)->count());
    }

    public function test_editing_without_touching_the_password_keeps_it(): void
    {
        $coach = User::factory()->coachOf(Club::factory()->create())->create(['password' => Hash::make('la-de-siempre')]);

        Livewire::test(EditUser::class, ['record' => $coach->getRouteKey()])
            ->fillForm(['name' => 'Nombre nuevo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nombre nuevo', $coach->fresh()->name);
        $this->assertTrue(Hash::check('la-de-siempre', $coach->fresh()->password));
    }
}
