<?php

namespace Tests\Feature;

use App\Filament\Club\Pages\StartingXi;
use App\Models\Club;
use App\Models\Lineup;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El once ideal (Fase 10): formación y once huecos, guardados como huecos y
 * no como coordenadas (design D6).
 */
class StartingXiTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $season = Season::factory()->create(['is_current' => true]);
        $this->club = Club::factory()->create();
        $this->team = Team::factory()->create(['club_id' => $this->club->id, 'season_id' => $season->id]);
        $this->actingAs(User::factory()->coachOf($this->club)->create());
        Filament::setCurrentPanel('club');
    }

    /**
     * @return Collection<int, Player>
     */
    private function squad(int $count = 11)
    {
        return collect(range(1, $count))->map(function (int $number) {
            $player = Player::factory()->create(['club_id' => $this->club->id]);
            SquadMembership::factory()->create(['team_id' => $this->team->id, 'player_id' => $player->id, 'shirt_number' => $number]);

            return $player;
        });
    }

    public function test_the_page_renders(): void
    {
        Livewire::test(StartingXi::class)->assertOk();
    }

    public function test_a_formation_lays_out_eleven_slots(): void
    {
        $page = Livewire::test(StartingXi::class)->set('formation', '4-3-3')->instance();

        $slots = collect($page->rows())->flatten();

        $this->assertCount(11, $slots);
        $this->assertSame([1], $page->rows()[0], 'el portero es siempre el hueco 1');
        $this->assertCount(4, $page->rows()[1]);
        $this->assertCount(3, $page->rows()[2]);
        $this->assertCount(3, $page->rows()[3]);
    }

    public function test_the_coach_saves_a_starting_eleven(): void
    {
        $squad = $this->squad();

        Livewire::test(StartingXi::class)
            ->set('formation', '4-4-2')
            ->set('picks', $squad->pluck('id')->mapWithKeys(fn (int $id, int $index) => [$index + 1 => $id])->all())
            ->call('save');

        $lineup = Lineup::where('team_id', $this->team->id)->firstOrFail();

        $this->assertSame('4-4-2', $lineup->formation);
        $this->assertSame(11, $lineup->slots()->count());
        $this->assertSame($squad->first()->id, $lineup->slots()->where('slot', 1)->value('player_id'));
    }

    public function test_saving_again_replaces_the_previous_eleven(): void
    {
        $squad = $this->squad();
        $slots = $squad->pluck('id')->mapWithKeys(fn (int $id, int $index) => [$index + 1 => $id])->all();

        Livewire::test(StartingXi::class)->set('picks', $slots)->call('save');
        Livewire::test(StartingXi::class)->set('picks', [1 => $squad->last()->id])->call('save');

        $lineup = Lineup::where('team_id', $this->team->id)->firstOrFail();

        $this->assertSame(1, $lineup->slots()->count());
        $this->assertSame($squad->last()->id, $lineup->slots()->first()->player_id);
    }

    public function test_the_same_player_cannot_hold_two_slots(): void
    {
        $squad = $this->squad(2);

        Livewire::test(StartingXi::class)
            ->set('picks', [1 => $squad->first()->id, 2 => $squad->first()->id])
            ->call('save');

        $this->assertSame(0, Lineup::where('team_id', $this->team->id)->count());
    }

    public function test_an_unfinished_eleven_is_allowed(): void
    {
        $squad = $this->squad(3);

        Livewire::test(StartingXi::class)
            ->set('picks', [1 => $squad->first()->id, 5 => $squad->last()->id])
            ->call('save');

        $this->assertSame(2, Lineup::where('team_id', $this->team->id)->firstOrFail()->slots()->count());
    }

    public function test_an_existing_eleven_is_loaded_when_the_page_opens(): void
    {
        $squad = $this->squad(2);
        $lineup = Lineup::create(['team_id' => $this->team->id, 'formation' => '3-5-2']);
        $lineup->slots()->create(['player_id' => $squad->first()->id, 'slot' => 1]);

        Livewire::test(StartingXi::class)
            ->assertSet('formation', '3-5-2')
            ->assertSet('picks.1', $squad->first()->id);
    }

    public function test_only_this_seasons_squad_can_be_picked(): void
    {
        $this->squad(2);
        Player::factory()->create(['club_id' => $this->club->id]);
        $foreign = Player::factory()->create();

        $options = Livewire::test(StartingXi::class)->instance()->squad();

        $this->assertCount(2, $options, 'sólo los inscritos en la plantilla de esta temporada');
        $this->assertFalse($options->contains('id', $foreign->id));
    }

    public function test_an_unknown_formation_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Lineup::create(['team_id' => $this->team->id, 'formation' => '9-0-1']);
    }
}
