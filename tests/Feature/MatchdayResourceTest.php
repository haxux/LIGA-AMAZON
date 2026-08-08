<?php

namespace Tests\Feature;

use App\Filament\Resources\Matchdays\Pages\CreateMatchday;
use App\Filament\Resources\Matchdays\Pages\EditMatchday;
use App\Filament\Resources\Matchdays\Pages\ListMatchdays;
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

        Livewire::test(CreateMatchday::class)
            ->fillForm([
                'season_id' => $season->id,
                'number' => 1,
                'date' => '2026-08-15',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('matchdays', [
            'season_id' => $season->id,
            'number' => 1,
        ]);
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

    public function test_duplicate_matchday_number_within_same_season_is_rejected_as_a_form_error(): void
    {
        $season = Season::factory()->create();
        Matchday::factory()->create(['season_id' => $season->id, 'number' => 1]);

        Livewire::test(CreateMatchday::class)
            ->fillForm([
                'season_id' => $season->id,
                'number' => 1,
                'date' => '2026-08-15',
            ])
            ->call('create')
            ->assertHasFormErrors(['number']);

        $this->assertSame(1, Matchday::where('season_id', $season->id)->where('number', 1)->count());
    }

    public function test_duplicate_matchday_number_across_different_seasons_is_allowed(): void
    {
        $seasonA = Season::factory()->create();
        $seasonB = Season::factory()->create();
        Matchday::factory()->create(['season_id' => $seasonA->id, 'number' => 1]);

        Livewire::test(CreateMatchday::class)
            ->fillForm([
                'season_id' => $seasonB->id,
                'number' => 1,
                'date' => '2026-08-15',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Matchday::where('season_id', $seasonB->id)->where('number', 1)->count());
    }
}
