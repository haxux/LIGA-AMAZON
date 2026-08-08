<?php

namespace Tests\Feature;

use App\Filament\Resources\Players\Pages\CreatePlayer;
use App\Filament\Resources\Players\Pages\EditPlayer;
use App\Filament\Resources\Players\Pages\ListPlayers;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlayerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListPlayers::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreatePlayer::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        $player = Player::factory()->create();

        Livewire::test(EditPlayer::class, ['record' => $player->getRouteKey()])->assertOk();
    }

    public function test_can_create_a_player_via_the_form(): void
    {
        $team = Team::factory()->create();

        Livewire::test(CreatePlayer::class)
            ->fillForm([
                'team_id' => $team->id,
                'name' => 'Rivaldo Nunes',
                'position' => 'Forward',
                'birth_date' => '1998-04-12',
                'shirt_number' => 9,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('players', [
            'team_id' => $team->id,
            'name' => 'Rivaldo Nunes',
        ]);
    }

    public function test_can_edit_a_player_via_the_form(): void
    {
        $player = Player::factory()->create(['name' => 'Old Name']);

        Livewire::test(EditPlayer::class, ['record' => $player->getRouteKey()])
            ->fillForm([
                'name' => 'New Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'name' => 'New Name',
        ]);
    }

    public function test_duplicate_shirt_number_within_same_team_is_rejected_as_a_form_error(): void
    {
        $team = Team::factory()->create();
        Player::factory()->create(['team_id' => $team->id, 'shirt_number' => 7]);

        Livewire::test(CreatePlayer::class)
            ->fillForm([
                'team_id' => $team->id,
                'name' => 'Another Player',
                'position' => 'Midfielder',
                'shirt_number' => 7,
            ])
            ->call('create')
            ->assertHasFormErrors(['shirt_number']);

        $this->assertSame(1, Player::where('team_id', $team->id)->where('shirt_number', 7)->count());
    }

    public function test_duplicate_shirt_number_across_different_teams_is_allowed(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        Player::factory()->create(['team_id' => $teamA->id, 'shirt_number' => 7]);

        Livewire::test(CreatePlayer::class)
            ->fillForm([
                'team_id' => $teamB->id,
                'name' => 'Another Player',
                'position' => 'Midfielder',
                'shirt_number' => 7,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Player::where('team_id', $teamB->id)->where('shirt_number', 7)->count());
    }

    public function test_position_filter_returns_only_matching_records(): void
    {
        $team = Team::factory()->create();
        $goalkeeper = Player::factory()->create(['team_id' => $team->id, 'position' => 'Goalkeeper', 'shirt_number' => 1]);
        $forward = Player::factory()->create(['team_id' => $team->id, 'position' => 'Forward', 'shirt_number' => 9]);

        Livewire::test(ListPlayers::class)
            ->filterTable('position', 'Goalkeeper')
            ->assertCanSeeTableRecords([$goalkeeper])
            ->assertCanNotSeeTableRecords([$forward]);
    }
}
