<?php

namespace Tests\Feature;

use App\Enums\CupDesign;
use App\Enums\CupScope;
use App\Filament\Resources\Cups\Pages\CreateCup;
use App\Models\Cup;
use App\Models\CupRound;
use App\Models\CupTie;
use App\Models\Division;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Copas nacionales e internacionales: las segundas llevan un diseño propio
 * (Libertadores, Sudamericana) y las primeras siguen como siempre.
 */
class InternationalCupTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
    }

    public function test_a_national_cup_has_no_theme(): void
    {
        $cup = Cup::factory()->create(['season_id' => $this->season->id]);

        $this->assertNull($cup->theme());
        $this->assertSame('', $cup->themeStyle());

        $this->get(route('site.cups.show', $cup))->assertOk()->assertDontSee('COPA INTERNACIONAL');
    }

    public function test_an_international_cup_page_wears_its_design(): void
    {
        $cup = Cup::factory()->international(CupDesign::Libertadores)
            ->create(['season_id' => $this->season->id, 'name' => 'Libertadores']);

        $this->get(route('site.cups.show', $cup))
            ->assertOk()
            ->assertSee('COPA INTERNACIONAL')
            ->assertSee('--color-brand:'.CupDesign::Libertadores->palette()['brand'], false);

        $this->assertNotSame(CupDesign::Libertadores->palette(), CupDesign::Sudamericana->palette());
    }

    public function test_a_leftover_design_does_not_theme_a_national_cup(): void
    {
        $cup = Cup::factory()->create([
            'season_id' => $this->season->id,
            'scope' => CupScope::National,
            'design' => CupDesign::Sudamericana,
        ]);

        $this->assertNull($cup->theme());
    }

    public function test_the_cups_list_and_its_matches_carry_the_theme(): void
    {
        $cup = Cup::factory()->international(CupDesign::Sudamericana)
            ->create(['season_id' => $this->season->id, 'name' => 'Sudamericana']);
        $division = Division::factory()->create(['season_id' => $this->season->id]);
        $home = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id]);
        $away = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id]);
        $round = CupRound::create(['cup_id' => $cup->id, 'name' => 'Final', 'position' => 1, 'legs' => 1]);
        $tie = CupTie::create(['cup_round_id' => $round->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $game = Game::create(['cup_tie_id' => $tie->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);

        $color = '--color-brand:'.CupDesign::Sudamericana->palette()['brand'];

        $this->get(route('site.cups.index'))->assertOk()->assertSee($color, false)->assertSee('COPA SUDAMERICANA');
        $this->get(route('site.games.show', $game))->assertOk();
        $this->get(route('site.fixtures'))->assertOk()->assertSee($color, false);
    }

    public function test_the_admin_creates_an_international_cup_with_a_design(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateCup::class)
            ->fillForm([
                'season_id' => $this->season->id,
                'name' => 'Copa Libertadores',
                'scope' => CupScope::International->value,
                'design' => CupDesign::Libertadores->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $cup = Cup::where('name', 'Copa Libertadores')->firstOrFail();
        $this->assertSame(CupDesign::Libertadores, $cup->design);
        $this->assertTrue($cup->isInternational());
    }

    public function test_an_international_cup_requires_a_design(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateCup::class)
            ->fillForm([
                'season_id' => $this->season->id,
                'name' => 'Sin diseño',
                'scope' => CupScope::International->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['design' => 'required']);
    }
}
