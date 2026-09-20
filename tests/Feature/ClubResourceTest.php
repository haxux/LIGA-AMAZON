<?php

namespace Tests\Feature;

use App\Filament\Resources\Clubs\Pages\CreateClub;
use App\Filament\Resources\Clubs\Pages\EditClub;
use App\Filament\Resources\Clubs\Pages\ListClubs;
use App\Models\Club;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClubResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListClubs::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreateClub::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        Livewire::test(EditClub::class, ['record' => Club::factory()->create()->getRouteKey()])->assertOk();
    }

    public function test_can_create_a_club_via_the_form(): void
    {
        Livewire::test(CreateClub::class)
            ->fillForm([
                'name' => 'Rio Branco EC',
                'short_name' => 'RBR',
                'founded_year' => 1998,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clubs', ['name' => 'Rio Branco EC', 'short_name' => 'RBR']);
    }

    public function test_duplicate_club_name_is_rejected_as_a_form_error(): void
    {
        Club::factory()->create(['name' => 'Manaos FC']);

        Livewire::test(CreateClub::class)
            ->fillForm([
                'name' => 'Manaos FC',
                'short_name' => 'MA2',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(1, Club::where('name', 'Manaos FC')->count());
    }

    /**
     * Renombrar el club renombra su participación en TODAS las temporadas, que
     * es justamente lo que la entidad existe para conseguir.
     */
    public function test_renaming_a_club_renames_every_season_it_played(): void
    {
        $club = Club::factory()->create(['name' => 'Manaos FC']);
        $teams = collect([Season::factory()->create(), Season::factory()->create()])
            ->map(fn (Season $season) => Team::factory()->create(['season_id' => $season->id, 'club_id' => $club->id]));

        Livewire::test(EditClub::class, ['record' => $club->getRouteKey()])
            ->fillForm(['name' => 'Manaos Fútbol Club'])
            ->call('save')
            ->assertHasNoFormErrors();

        foreach ($teams as $team) {
            $this->assertSame('Manaos Fútbol Club', $team->fresh()->name);
        }
    }
}
