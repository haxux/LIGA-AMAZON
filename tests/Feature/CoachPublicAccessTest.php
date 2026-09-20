<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El acceso del técnico vive en la web pública, pero sin sesión la web tiene
 * que quedar exactamente como estaba (design D14).
 */
class CoachPublicAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Season::factory()->create(['is_current' => true]);
    }

    public function test_a_visitor_without_a_session_sees_no_crest(): void
    {
        $response = $this->get(route('site.standings'))->assertOk();

        $response->assertDontSee('id="coach-club"', false);
    }

    public function test_a_visitor_without_a_session_is_offered_a_way_in(): void
    {
        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee(route('filament.club.auth.login'), false)
            ->assertSee('Acceso técnicos');
    }

    public function test_a_logged_in_coach_sees_their_club_crest_in_the_header(): void
    {
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        $coach = User::factory()->coachOf($club)->create();

        $response = $this->actingAs($coach)->get(route('site.standings'))->assertOk();

        $response->assertSee('id="coach-club"', false);
        $response->assertSee('Manaos FC');
        $response->assertSee(url('/club'), false);
    }

    /**
     * El administrador no dirige ningún club, así que no hay escudo que
     * mostrarle: su panel es otro.
     */
    public function test_a_logged_in_administrator_sees_no_club_crest(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('site.standings'))
            ->assertOk()
            ->assertDontSee('id="coach-club"', false);
    }
}
