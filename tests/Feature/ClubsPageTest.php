<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Division;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El listado de Equipos (Fase 11): los clubes de una temporada, por división.
 */
class ClubsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_clubs_of_the_current_season_by_division(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        $segunda = Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);
        Team::factory()->for($season)->create(['division_id' => $primera->id, 'name' => 'Manaos FC']);
        Team::factory()->for($season)->create(['division_id' => $segunda->id, 'name' => 'Iquitos CD']);

        $this->get(route('site.clubs.index'))
            ->assertOk()
            ->assertSee('Primera')
            ->assertSee('Segunda')
            ->assertSee('Manaos FC')
            ->assertSee('Iquitos CD');
    }

    public function test_it_follows_the_season_filter(): void
    {
        $current = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $past = Season::factory()->create(['name' => '2025/26']);
        Team::factory()->for($current)->create(['name' => 'Club de Hoy']);
        Team::factory()->for($past)->create(['name' => 'Club de Ayer']);

        $this->get(route('site.clubs.index'))
            ->assertSee('Club de Hoy')
            ->assertDontSee('Club de Ayer');

        $this->get(route('site.clubs.index', ['temporada' => $past->id]))
            ->assertSee('Club de Ayer')
            ->assertDontSee('Club de Hoy');
    }

    /**
     * Un equipo sin división todavía no compite en nada, y el propietario pidió
     * que no se liste (reemplaza a la regla anterior, que lo enseñaba aparte).
     */
    public function test_a_club_without_a_division_is_not_listed(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        Team::factory()->for($season)->create(['division_id' => $primera->id, 'name' => 'Con Division FC']);
        Team::factory()->for($season)->create(['division_id' => null, 'name' => 'Sin Asignar FC']);

        $this->get(route('site.clubs.index'))
            ->assertOk()
            ->assertSee('Con Division FC')
            ->assertDontSee('Sin Asignar FC')
            ->assertDontSee('Sin división');
    }

    public function test_each_club_links_to_its_profile(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        Team::factory()->for($season)->create(['club_id' => $club->id]);

        $this->get(route('site.clubs.index'))
            ->assertSee(route('site.clubs.show', ['club' => $club->id]), escape: false);
    }

    public function test_the_section_is_in_the_public_navigation(): void
    {
        Season::factory()->create(['is_current' => true]);

        $this->get(route('site.standings'))
            ->assertSee(route('site.clubs.index'), escape: false)
            ->assertSee('Equipos');
    }
}
