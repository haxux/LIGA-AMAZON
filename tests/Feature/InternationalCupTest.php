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
use App\Services\Competition;
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

        $this->get(route('site.cups.show', $cup))->assertOk()->assertDontSee('cup-stage');
    }

    public function test_an_international_cup_page_wears_its_design(): void
    {
        $cup = Cup::factory()->international(CupDesign::Libertadores)
            ->create(['season_id' => $this->season->id, 'name' => 'Libertadores']);

        $this->get(route('site.cups.show', $cup))
            ->assertOk()
            ->assertSee('cup-stage cup-stage-libertadores', false)
            ->assertSee('--color-brand:'.CupDesign::Libertadores->palette()['brand'], false)
            ->assertSee('Fase final');

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
        $this->get(route('site.games.show', $game))->assertOk()->assertSee('cup-stage-sudamericana', false);

        // Partidos no enseña las internacionales, ni con `?competicion=todo` ni
        // pidiéndola por su clave (decisión del propietario, 2026-10-20): se
        // ven sólo en la página de la copa.
        $this->get(route('site.fixtures', ['competicion' => Competition::ALL]))
            ->assertOk()
            ->assertDontSee($color, false)
            ->assertDontSee('Sudamericana');

        $this->get(route('site.fixtures', ['competicion' => 'copa:'.$cup->id]))
            ->assertOk()
            ->assertDontSee('Sudamericana');
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

    public function test_the_cups_menu_splits_national_from_international(): void
    {
        Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa Amazonas']);
        Cup::factory()->international(CupDesign::Libertadores)
            ->create(['season_id' => $this->season->id, 'name' => 'Libertadores Amazon']);

        $this->get(route('site.home'))
            ->assertSee('Copas nacionales')
            ->assertSee('Copas internacionales');

        $this->get(route('site.cups.index', ['tipo' => 'nacionales']))
            ->assertOk()->assertSee('Copa Amazonas')->assertDontSee('Libertadores Amazon');

        $this->get(route('site.cups.index', ['tipo' => 'internacionales']))
            ->assertOk()->assertSee('Libertadores Amazon')->assertDontSee('Copa Amazonas');

        $this->get(route('site.cups.index'))
            ->assertOk()->assertSee('Copa Amazonas')->assertSee('Libertadores Amazon');
    }
}
