<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Trophies\Pages\ListTrophies;
use App\Filament\Club\Resources\Trophies\TrophyResource;
use App\Filament\Resources\Trophies\Pages\CreateTrophy;
use App\Models\Club;
use App\Models\Season;
use App\Models\Trophy;
use App\Models\User;
use Filament\Facades\Filament;
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

    public function test_the_coach_sees_only_their_clubs_trophies_and_cannot_award_any(): void
    {
        $club = Club::factory()->create();
        $coach = User::factory()->coachOf($club)->create();
        $own = Trophy::factory()->create(['club_id' => $club->id]);
        $foreign = Trophy::factory()->create();

        $this->actingAs($coach);
        Filament::setCurrentPanel('club');

        Livewire::test(ListTrophies::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);

        $this->assertFalse(TrophyResource::canCreate());
    }
}
