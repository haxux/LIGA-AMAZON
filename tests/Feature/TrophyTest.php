<?php

namespace Tests\Feature;

use App\Filament\Resources\Trophies\Pages\CreateTrophy;
use App\Models\Club;
use App\Models\Season;
use App\Models\Trophy;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los trofeos los otorga el administrador y los lee el técnico (Fase 10).
 */
class TrophyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_trophy_belongs_to_a_club_and_a_season(): void
    {
        $trophy = Trophy::factory()->create(['name' => 'Liga']);

        $this->assertNotNull($trophy->club);
        $this->assertNotNull($trophy->season);
        $this->assertTrue($trophy->club->trophies->contains($trophy));
    }

    public function test_the_same_trophy_cannot_be_awarded_twice_in_one_season(): void
    {
        $trophy = Trophy::factory()->create(['name' => 'Liga']);

        $this->expectException(QueryException::class);

        Trophy::factory()->create([
            'club_id' => $trophy->club_id,
            'season_id' => $trophy->season_id,
            'name' => 'Liga',
        ]);
    }

    /**
     * El título se gana en una temporada y se exhibe en todas: es la razón de
     * que cuelgue del club y no del equipo-temporada (Fase 9).
     */
    public function test_a_trophy_survives_the_season_it_was_won_in(): void
    {
        $club = Club::factory()->create();
        $won = Season::factory()->create(['name' => '2025/26']);
        Trophy::factory()->create(['club_id' => $club->id, 'season_id' => $won->id, 'name' => 'Copa']);

        Season::factory()->create(['name' => '2026/27', 'is_current' => true]);

        $this->assertSame(1, $club->fresh()->trophies()->count());
    }

    public function test_the_administrator_awards_a_trophy(): void
    {
        $this->actingAs(User::factory()->create());
        $club = Club::factory()->create();
        $season = Season::factory()->create();

        Livewire::test(CreateTrophy::class)
            ->fillForm(['club_id' => $club->id, 'season_id' => $season->id, 'name' => 'Supercopa'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('trophies', ['club_id' => $club->id, 'name' => 'Supercopa']);
    }

    public function test_a_duplicate_award_is_rejected_as_a_form_error(): void
    {
        $this->actingAs(User::factory()->create());
        $trophy = Trophy::factory()->create(['name' => 'Liga']);

        Livewire::test(CreateTrophy::class)
            ->fillForm(['club_id' => $trophy->club_id, 'season_id' => $trophy->season_id, 'name' => 'Liga'])
            ->call('create')
            ->assertHasFormErrors(['name']);
    }

    /**
     * El palmarés se lee en la ficha pública del club, así que el panel del
     * técnico ya no lo repite: una segunda pantalla para lo mismo sólo añade
     * sitios donde mirar.
     */
    public function test_the_coach_panel_no_longer_lists_trophies(): void
    {
        $club = Club::factory()->create();
        $coach = User::factory()->coachOf($club)->create();
        Trophy::factory()->create(['club_id' => $club->id, 'name' => 'Copa Amazonas']);

        $this->actingAs($coach, 'club');

        $this->get('/club/trofeos')->assertNotFound();

        // Y donde sí se leen es en la ficha pública, sin sesión ninguna.
        $this->get(route('site.clubs.show', ['club' => $club->id, 'tab' => 'trofeos']))
            ->assertOk()
            ->assertSee('Copa Amazonas');
    }
}
