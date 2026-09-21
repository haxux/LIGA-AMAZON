<?php

namespace Tests\Feature;

use App\Livewire\StartingElevenEditor;
use App\Models\Club;
use App\Models\Lineup;
use App\Models\LineupSlot;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El once ideal se arma en la ficha pública del club, donde todo el mundo lo ve.
 *
 * Vive ahí y no en el panel del técnico (decisión del propietario al cerrar la
 * Fase 13): dos pantallas escribiendo la misma alineación acaban separándose.
 */
class StartingElevenEditorTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $club;

    private Team $team;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->club = Club::factory()->create(['name' => 'Manaos FC']);
        $this->team = Team::factory()->create(['club_id' => $this->club->id, 'season_id' => $this->season->id]);
        $this->coach = User::factory()->coachOf($this->club)->create();
    }

    /**
     * @return Collection<int, Player>
     */
    private function squad(int $count = 11): Collection
    {
        return collect(range(1, $count))->map(function (int $number) {
            $player = Player::factory()->create(['club_id' => $this->club->id]);
            SquadMembership::factory()->create([
                'team_id' => $this->team->id,
                'player_id' => $player->id,
                'shirt_number' => $number,
            ]);

            return $player;
        });
    }

    private function editor(?Team $team = null): Testable
    {
        return Livewire::test(StartingElevenEditor::class, ['team' => $team ?? $this->team]);
    }

    private function asCoach(?User $coach = null): void
    {
        $this->actingAs($coach ?? $this->coach, 'club');
    }

    public function test_a_formation_lays_out_eleven_slots(): void
    {
        $this->asCoach();

        $page = $this->editor()->set('formation', '4-3-3')->instance();

        $this->assertCount(11, collect($page->rows())->flatten());
        $this->assertSame([1], $page->rows()[0], 'el portero es siempre el hueco 1');
        $this->assertCount(4, $page->rows()[1]);
        $this->assertCount(3, $page->rows()[2]);
        $this->assertCount(3, $page->rows()[3]);
    }

    public function test_the_coach_places_players_and_saves_with_the_button(): void
    {
        $this->asCoach();
        $squad = $this->squad();

        $page = $this->editor()->set('formation', '4-4-2');

        foreach ($squad as $index => $player) {
            $page->call('place', $index + 1, $player->id);
        }

        // Nada se ha escrito todavía: el guardado es con botón, a propósito.
        $this->assertSame(0, Lineup::query()->count());

        $page->call('save')->assertSet('saved', true);

        $this->assertSame('4-4-2', Lineup::query()->first()->formation);
        $this->assertSame(11, LineupSlot::query()->count());
    }

    public function test_placing_a_player_again_moves_them_instead_of_repeating(): void
    {
        $this->asCoach();
        $squad = $this->squad(3);

        $this->editor()
            ->call('place', 1, $squad->first()->id)
            ->call('place', 5, $squad->get(1)->id)
            ->call('place', 7, $squad->first()->id)
            ->assertSet('picks.1', null)
            ->assertSet('picks.7', $squad->first()->id)
            ->call('save')
            ->assertSet('error', null);

        $this->assertSame(2, LineupSlot::query()->count());
    }

    public function test_a_slot_can_be_emptied(): void
    {
        $this->asCoach();
        $squad = $this->squad(2);

        $this->editor()
            ->call('place', 1, $squad->first()->id)
            ->call('place', 1)
            ->assertSet('picks.1', null)
            ->call('save');

        $this->assertSame(0, LineupSlot::query()->count());
    }

    public function test_an_unfinished_eleven_is_allowed(): void
    {
        $this->asCoach();
        $squad = $this->squad(2);

        $this->editor()
            ->call('place', 1, $squad->first()->id)
            ->call('place', 5, $squad->last()->id)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertSame(2, LineupSlot::query()->count());
    }

    /**
     * Sólo la plantilla de esta temporada, aunque el id llegue tecleado: la
     * petición de Livewire no pasa por la página, así que el filtro va aquí.
     */
    public function test_a_player_outside_the_squad_cannot_be_placed(): void
    {
        $this->asCoach();
        $stranger = Player::factory()->create();

        $this->editor()
            ->call('place', 1, $stranger->id)
            ->assertSet('picks.1', null);
    }

    public function test_changing_formation_keeps_whoever_still_fits(): void
    {
        $this->asCoach();
        $squad = $this->squad();

        $page = $this->editor()->set('formation', '4-4-2');

        foreach ($squad as $index => $player) {
            $page->call('place', $index + 1, $player->id);
        }

        $page->set('formation', '4-3-3')->assertSet('picks.1', $squad->first()->id);
    }

    public function test_an_existing_eleven_is_loaded_when_the_page_opens(): void
    {
        $this->asCoach();
        $squad = $this->squad(1);
        $lineup = Lineup::factory()->create(['team_id' => $this->team->id, 'formation' => '3-5-2']);
        LineupSlot::create(['lineup_id' => $lineup->id, 'player_id' => $squad->first()->id, 'slot' => 1]);

        $this->editor()
            ->assertSet('formation', '3-5-2')
            ->assertSet('picks.1', $squad->first()->id);
    }

    public function test_an_unknown_formation_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Lineup::factory()->create(['team_id' => $this->team->id, 'formation' => '9-1-0']);
    }

    // ── Quién puede editar ────────────────────────────────────────────────

    public function test_an_anonymous_visitor_cannot_edit(): void
    {
        $this->assertFalse($this->editor()->instance()->canEdit());
    }

    public function test_another_clubs_coach_cannot_edit(): void
    {
        $this->asCoach(User::factory()->coachOf(Club::factory()->create(['name' => 'Tapajós SC']))->create());

        $this->assertFalse($this->editor()->instance()->canEdit());
    }

    /**
     * El administrador entra por el guard `web`, no por el del técnico: en el
     * chat es un presidente, y aquí tampoco arma alineaciones.
     */
    public function test_an_administrator_cannot_edit_either(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->assertFalse($this->editor()->instance()->canEdit());
    }

    /**
     * Una alineación de un año cerrado es historia, no un borrador.
     */
    public function test_a_past_season_cannot_be_edited(): void
    {
        $this->asCoach();

        $past = Season::factory()->create(['name' => '2024/25']);
        $pastTeam = Team::factory()->create(['club_id' => $this->club->id, 'season_id' => $past->id]);

        $this->assertFalse($this->editor($pastTeam)->instance()->canEdit());
    }

    /**
     * Y el permiso se comprueba en CADA acción, no sólo al pintar el botón: la
     * petición de Livewire no pasa por la ruta pública.
     */
    public function test_someone_who_cannot_edit_changes_nothing(): void
    {
        $squad = $this->squad(1);

        $this->editor()
            ->call('place', 1, $squad->first()->id)
            ->assertSet('picks.1', null)
            ->call('save')
            ->assertSet('saved', false);

        $this->assertSame(0, Lineup::query()->count());
    }

    public function test_the_save_names_a_repeated_player(): void
    {
        $this->asCoach();
        $squad = $this->squad(1);

        $this->editor()
            ->set('picks', [1 => $squad->first()->id, 2 => $squad->first()->id])
            ->call('save')
            ->assertSet('saved', false);

        $this->assertStringContainsString($squad->first()->name, $this->editor()
            ->set('picks', [1 => $squad->first()->id, 2 => $squad->first()->id])
            ->call('save')
            ->get('error'));
    }

    // ── La página que lo monta ────────────────────────────────────────────

    public function test_the_club_page_gives_the_editor_only_to_its_coach(): void
    {
        $this->squad(1);
        $url = route('site.clubs.show', ['club' => $this->club->id]);

        // Visitante anónimo: la ficha de siempre, sin componente.
        $this->get($url)->assertOk()->assertDontSee('Guardar once');

        $this->asCoach();
        $this->get($url)->assertOk()->assertSee('Guardar once');
    }

    public function test_the_club_page_does_not_give_the_editor_for_a_past_season(): void
    {
        $past = Season::factory()->create(['name' => '2024/25']);
        Team::factory()->create(['club_id' => $this->club->id, 'season_id' => $past->id]);

        $this->asCoach();

        $this->get(route('site.clubs.show', ['club' => $this->club->id, 'temporada' => $past->id]))
            ->assertOk()
            ->assertDontSee('Guardar once');
    }

    public function test_the_club_panel_no_longer_serves_the_page(): void
    {
        $this->asCoach();

        $this->get('/club/once-ideal')->assertNotFound();
    }
}
