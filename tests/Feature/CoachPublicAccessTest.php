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

    /**
     * El escudo de la liga, en las tres formas que un navegador busca: el .ico
     * para quien pide /favicon.ico a secas, el png grande para la pestaña y el
     * de Apple para quien lo guarda en la pantalla de inicio.
     */
    public function test_every_page_carries_the_leagues_favicon(): void
    {
        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee(asset('favicon.ico'), false)
            ->assertSee(asset('favicon.png'), false)
            ->assertSee(asset('apple-touch-icon.png'), false);

        foreach (['favicon.ico', 'favicon.png', 'apple-touch-icon.png'] as $file) {
            $this->assertFileExists(public_path($file), "falta {$file} en public/");
        }
    }

    /**
     * Por el guard `club`, que es el de su panel: `auth()` a secas es el del
     * administrador, y con él la cabecera no reconocería a ningún técnico.
     */
    public function test_a_logged_in_coach_sees_their_club_crest_in_the_header(): void
    {
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        $coach = User::factory()->coachOf($club)->create();

        $response = $this->actingAs($coach, 'club')->get(route('site.standings'))->assertOk();

        $response->assertSee('id="coach-club"', false);
        $response->assertSee('Manaos FC');
        $response->assertSee(url('/club'), false);
    }

    /**
     * Su club y su chat, a un clic desde cualquier página del sitio.
     */
    public function test_a_logged_in_coach_gets_my_team_and_chat_in_the_nav(): void
    {
        $season = Season::query()->where('is_current', true)->first();
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        $coach = User::factory()->coachOf($club)->create();

        $this->actingAs($coach, 'club')->get(route('site.standings'))
            ->assertOk()
            ->assertSee('Mi equipo')
            ->assertSee(route('site.clubs.show', ['club' => $club->id, 'temporada' => $season->id]), false)
            ->assertSee('Chat')
            ->assertSee(route('site.chat'), false);
    }

    public function test_a_visitor_without_a_session_gets_neither(): void
    {
        $this->get(route('site.standings'))
            ->assertOk()
            ->assertDontSee('Mi equipo')
            ->assertDontSee(route('site.chat'), false);
    }

    /**
     * El chat cuelga de la sesión y no del rol: el presidente entra por la misma
     * puerta, aunque no dirija ningún club.
     */
    public function test_an_administrator_also_gets_the_chat_link(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('site.standings'))
            ->assertOk()
            ->assertSee('Chat')
            ->assertSee(route('site.chat'), false);
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
            ->assertDontSee('id="coach-club"', false)
            ->assertDontSee('Mi equipo');
    }
}
