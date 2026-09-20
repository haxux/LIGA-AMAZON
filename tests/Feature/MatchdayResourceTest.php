<?php

namespace Tests\Feature;

use App\Filament\Resources\Matchdays\Pages\CreateMatchday;
use App\Filament\Resources\Matchdays\Pages\EditMatchday;
use App\Filament\Resources\Matchdays\Pages\ListMatchdays;
use App\Models\Division;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MatchdayResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListMatchdays::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreateMatchday::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        $matchday = Matchday::factory()->create();

        Livewire::test(EditMatchday::class, ['record' => $matchday->getRouteKey()])->assertOk();
    }

    public function test_can_create_a_matchday_via_the_form(): void
    {
        $season = Season::factory()->create();
        $division = Division::factory()->create(['season_id' => $season->id]);

        Livewire::test(CreateMatchday::class)
            ->fillForm([
                'season_id' => $season->id,
                'division_id' => $division->id,
                'number' => 1,
                'date' => '2026-08-15',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('matchdays', [
            'season_id' => $season->id,
            'division_id' => $division->id,
            'number' => 1,
        ]);
    }

    public function test_the_division_is_required(): void
    {
        $season = Season::factory()->create();

        Livewire::test(CreateMatchday::class)
            ->fillForm([
                'season_id' => $season->id,
                'number' => 1,
                'date' => '2026-08-15',
            ])
            ->call('create')
            ->assertHasFormErrors(['division_id']);
    }

    /**
     * The division Select is scoped to the chosen season — divisions belong
     * to a season, so offering another season's would let the admin build a
     * matchday the model guard then refuses to save.
     */
    public function test_the_division_options_are_limited_to_the_chosen_season(): void
    {
        $season = Season::factory()->create();
        $ownDivision = Division::factory()->create(['season_id' => $season->id]);
        $foreignDivision = Division::factory()->create();

        $options = Livewire::test(CreateMatchday::class)
            ->fillForm(['season_id' => $season->id])
            ->instance()
            ->getSchemaComponent('form.division_id')
            ->getOptions();

        $this->assertArrayHasKey($ownDivision->id, $options);
        $this->assertArrayNotHasKey($foreignDivision->id, $options);
    }

    public function test_changing_the_season_clears_the_selected_division(): void
    {
        $season = Season::factory()->create();
        $division = Division::factory()->create(['season_id' => $season->id]);
        $otherSeason = Season::factory()->create();

        Livewire::test(CreateMatchday::class)
            ->fillForm(['season_id' => $season->id, 'division_id' => $division->id])
            ->fillForm(['season_id' => $otherSeason->id])
            ->assertFormSet(['division_id' => null]);
    }

    public function test_can_edit_a_matchday_via_the_form(): void
    {
        $matchday = Matchday::factory()->create(['number' => 1]);

        Livewire::test(EditMatchday::class, ['record' => $matchday->getRouteKey()])
            ->fillForm([
                'number' => 2,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('matchdays', [
            'id' => $matchday->id,
            'number' => 2,
        ]);
    }

    public function test_duplicate_matchday_number_within_same_division_is_rejected_as_a_form_error(): void
    {
        $season = Season::factory()->create();
        $division = Division::factory()->create(['season_id' => $season->id]);
        Matchday::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'number' => 1]);

        Livewire::test(CreateMatchday::class)
            ->fillForm([
                'season_id' => $season->id,
                'division_id' => $division->id,
                'number' => 1,
                'date' => '2026-08-15',
            ])
            ->call('create')
            ->assertHasFormErrors(['number']);

        $this->assertSame(1, Matchday::where('division_id', $division->id)->where('number', 1)->count());
    }

    public function test_duplicate_matchday_number_across_divisions_of_one_season_is_allowed(): void
    {
        $season = Season::factory()->create();
        $primera = Division::factory()->create(['season_id' => $season->id]);
        $segunda = Division::factory()->create(['season_id' => $season->id]);
        Matchday::factory()->create(['season_id' => $season->id, 'division_id' => $primera->id, 'number' => 1]);

        Livewire::test(CreateMatchday::class)
            ->fillForm([
                'season_id' => $season->id,
                'division_id' => $segunda->id,
                'number' => 1,
                'date' => '2026-08-15',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Matchday::where('division_id', $segunda->id)->where('number', 1)->count());
    }

    public function test_duplicate_matchday_number_across_different_seasons_is_allowed(): void
    {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();
        $divisionB = Division::factory()->create(['season_id' => $seasonB->id]);
        Matchday::factory()->create(['season_id' => $seasonA->id, 'number' => 1]);

        Livewire::test(CreateMatchday::class)
            ->fillForm([
                'season_id' => $seasonB->id,
                'division_id' => $divisionB->id,
                'number' => 1,
                'date' => '2026-08-15',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Matchday::where('season_id', $seasonB->id)->where('number', 1)->count());
    }
}
