<?php

namespace Tests\Feature;

use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Matchdays\Pages\EditMatchday;
use App\Models\Club;
use App\Models\Cup;
use App\Models\CupGroup;
use App\Models\CupTeam;
use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use App\Services\ClubSeasonService;
use App\Services\Competition;
use App\Services\GoalscorersService;
use App\Services\PlayerStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El MVP del partido y el jugador de la jornada (Week MVP): elecciones
 * opcionales del administrador, no eventos, que cuentan en las estadísticas del
 * jugador y del club y se ven en el partido, en la jornada y en los rankings.
 */
class MvpTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Division $division;

    private Team $home;

    private Team $away;

    private Player $homeStar;

    private Player $awayStar;

    private Matchday $matchday;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026-I']);
        $this->division = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Super League']);

        $this->home = $this->team('Alfa FC');
        $this->away = $this->team('Beta FC');

        $this->homeStar = $this->player($this->home, 'Estrella Local');
        $this->awayStar = $this->player($this->away, 'Estrella Visita');

        $this->matchday = Matchday::factory()->create(['season_id' => $this->season->id, 'division_id' => $this->division->id, 'number' => 1]);
        $this->game = Game::factory()->create([
            'matchday_id' => $this->matchday->id, 'home_team_id' => $this->home->id, 'away_team_id' => $this->away->id,
            'home_score' => 2, 'away_score' => 1,
        ]);
    }

    private function team(string $name): Team
    {
        return Team::factory()->create([
            'season_id' => $this->season->id, 'division_id' => $this->division->id,
            'club_id' => Club::factory()->create(['name' => $name, 'short_name' => strtoupper(substr($name, 0, 3))])->id,
            'name' => $name,
        ]);
    }

    private function player(Team $team, string $name): Player
    {
        $player = Player::factory()->create(['club_id' => $team->club_id, 'name' => $name]);
        SquadMembership::factory()->create(['team_id' => $team->id, 'player_id' => $player->id]);

        return $player;
    }

    // ── Opcional, y de quien juega ────────────────────────────────────────

    public function test_both_choices_are_optional(): void
    {
        $this->assertNull($this->game->fresh()->mvp_player_id);
        $this->assertNull($this->matchday->fresh()->week_mvp_player_id);
        $this->assertNull($this->game->fresh()->mvp);
    }

    public function test_the_mvp_can_be_a_player_of_either_team(): void
    {
        $this->game->update(['mvp_player_id' => $this->awayStar->id]);

        $this->assertTrue($this->game->fresh()->mvp->is($this->awayStar));
    }

    public function test_the_mvp_must_belong_to_one_of_the_two_teams(): void
    {
        $outsider = $this->player($this->team('Gamma FC'), 'De Otro');

        $this->expectException(ValidationException::class);

        $this->game->update(['mvp_player_id' => $outsider->id]);
    }

    public function test_someone_who_already_left_the_squad_can_still_be_the_mvp(): void
    {
        SquadMembership::query()->where('player_id', $this->homeStar->id)->first()->close();

        $this->game->update(['mvp_player_id' => $this->homeStar->id]);

        $this->assertSame($this->homeStar->id, $this->game->fresh()->mvp_player_id);
    }

    public function test_voiding_a_game_removes_its_mvp(): void
    {
        $this->game->update(['mvp_player_id' => $this->homeStar->id]);

        $this->game->voidAgainst('home');

        $this->assertNull($this->game->fresh()->mvp_player_id);
    }

    public function test_the_week_mvp_must_play_in_that_division(): void
    {
        $other = Division::factory()->create(['season_id' => $this->season->id]);
        $foreign = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $other->id]);
        $outsider = $this->player($foreign, 'De Otra División');

        $this->matchday->update(['week_mvp_player_id' => $this->homeStar->id]);
        $this->assertSame($this->homeStar->id, $this->matchday->fresh()->week_mvp_player_id);

        $this->expectException(ValidationException::class);

        $this->matchday->update(['week_mvp_player_id' => $outsider->id]);
    }

    // ── Estadísticas del jugador ──────────────────────────────────────────

    public function test_the_player_totals_count_both_awards(): void
    {
        $this->game->update(['mvp_player_id' => $this->homeStar->id]);
        $this->matchday->update(['week_mvp_player_id' => $this->homeStar->id]);

        $totals = app(PlayerStatsService::class)->totals($this->homeStar, $this->season);

        $this->assertSame(1, $totals['mvp']);
        $this->assertSame(1, $totals['week_mvp']);
        $this->assertSame(0, app(PlayerStatsService::class)->totals($this->awayStar, $this->season)['mvp']);
    }

    public function test_the_career_totals_add_up_across_seasons(): void
    {
        $this->game->update(['mvp_player_id' => $this->homeStar->id]);

        $old = Season::factory()->create(['is_current' => false]);
        $oldDivision = Division::factory()->create(['season_id' => $old->id]);
        $oldHome = Team::factory()->create(['season_id' => $old->id, 'division_id' => $oldDivision->id]);
        $oldAway = Team::factory()->create(['season_id' => $old->id, 'division_id' => $oldDivision->id]);
        SquadMembership::factory()->create(['team_id' => $oldHome->id, 'player_id' => $this->homeStar->id]);
        $oldMatchday = Matchday::factory()->create(['season_id' => $old->id, 'division_id' => $oldDivision->id]);
        Game::factory()->create(['matchday_id' => $oldMatchday->id, 'home_team_id' => $oldHome->id, 'away_team_id' => $oldAway->id, 'home_score' => 1, 'away_score' => 0, 'mvp_player_id' => $this->homeStar->id]);

        $stats = app(PlayerStatsService::class);

        $this->assertSame(1, $stats->totals($this->homeStar, $this->season)['mvp']);
        $this->assertSame(2, $stats->totals($this->homeStar)['mvp']);
    }

    public function test_a_cup_game_mvp_counts_in_that_cup_and_not_in_the_league(): void
    {
        $cup = Cup::factory()->withGroups()->create(['season_id' => $this->season->id, 'name' => 'Copa X']);
        $group = CupGroup::create(['cup_id' => $cup->id, 'name' => 'A']);
        foreach ([$this->home, $this->away] as $team) {
            CupTeam::create(['cup_id' => $cup->id, 'team_id' => $team->id, 'cup_group_id' => $group->id]);
        }
        Game::create([
            'cup_group_id' => $group->id, 'group_matchday' => 1, 'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id, 'home_score' => 1, 'away_score' => 0, 'mvp_player_id' => $this->homeStar->id,
        ]);

        $stats = app(PlayerStatsService::class);

        $this->assertSame(1, $stats->totals($this->homeStar, $this->season, Competition::cup($cup))['mvp']);
        $this->assertSame(0, $stats->totals($this->homeStar, $this->season, Competition::league())['mvp']);
        $this->assertSame(1, $stats->totals($this->homeStar, $this->season)['mvp']);
    }

    public function test_the_week_mvp_does_not_exist_in_a_cup(): void
    {
        $this->matchday->update(['week_mvp_player_id' => $this->homeStar->id]);
        $cup = Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa Y']);

        $this->assertSame(0, app(PlayerStatsService::class)->totals($this->homeStar, $this->season, Competition::cup($cup))['week_mvp']);
        $this->assertSame(1, app(PlayerStatsService::class)->totals($this->homeStar, $this->season, Competition::league())['week_mvp']);
    }

    // ── Estadísticas del club ─────────────────────────────────────────────

    public function test_the_club_counts_the_awards_of_its_own_players_only(): void
    {
        $this->game->update(['mvp_player_id' => $this->homeStar->id]);
        $this->matchday->update(['week_mvp_player_id' => $this->awayStar->id]);

        $service = app(ClubSeasonService::class);

        $this->assertSame(1, $service->stats($this->home)->mvps);
        $this->assertSame(0, $service->stats($this->away)->mvps);
        $this->assertSame(0, $service->stats($this->home)->week_mvps);
        $this->assertSame(1, $service->stats($this->away)->week_mvps);
    }

    // ── Rankings ──────────────────────────────────────────────────────────

    public function test_the_leaderboards_rank_by_awards(): void
    {
        $second = Game::factory()->create([
            'matchday_id' => Matchday::factory()->create(['season_id' => $this->season->id, 'division_id' => $this->division->id, 'number' => 2])->id,
            'home_team_id' => $this->home->id, 'away_team_id' => $this->away->id, 'home_score' => 0, 'away_score' => 0,
        ]);

        $this->game->update(['mvp_player_id' => $this->homeStar->id]);
        $second->update(['mvp_player_id' => $this->homeStar->id]);
        $this->matchday->update(['week_mvp_player_id' => $this->awayStar->id]);

        $service = app(GoalscorersService::class);

        $mvps = $service->topMvps($this->season);
        $this->assertSame('Estrella Local', $mvps->first()->player->name);
        $this->assertSame(2, $mvps->first()->count);

        $week = $service->topWeekMvps($this->season);
        $this->assertSame('Estrella Visita', $week->first()->player->name);
        $this->assertCount(0, $service->topWeekMvps($this->season, 10, Competition::cup(Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Z']))));
    }

    // ── Lo que se ve ──────────────────────────────────────────────────────

    public function test_the_game_page_shows_the_mvp_only_when_there_is_one(): void
    {
        $this->get(route('site.games.show', $this->game))->assertOk()->assertDontSee('MVP DEL PARTIDO');

        $this->game->update(['mvp_player_id' => $this->homeStar->id]);

        $this->get(route('site.games.show', $this->game))
            ->assertOk()
            ->assertSee('MVP DEL PARTIDO')
            ->assertSee('Estrella Local');
    }

    public function test_the_fixtures_page_shows_the_week_mvp_next_to_the_matchday(): void
    {
        $this->get(route('site.fixtures'))->assertOk()->assertDontSee('WEEK MVP');

        $this->matchday->update(['week_mvp_player_id' => $this->awayStar->id]);

        $this->get(route('site.fixtures'))
            ->assertOk()
            ->assertSee('WEEK MVP · ESTRELLA VISITA');
    }

    public function test_the_player_page_shows_both_totals(): void
    {
        $this->game->update(['mvp_player_id' => $this->homeStar->id]);

        $this->get(route('site.players.show', $this->homeStar))
            ->assertOk()
            ->assertSee('MVP DEL PARTIDO')
            ->assertSee('WEEK MVP');
    }

    public function test_the_statistics_page_lists_the_awards(): void
    {
        $this->game->update(['mvp_player_id' => $this->homeStar->id]);

        $this->get(route('site.scorers'))
            ->assertOk()
            ->assertSee('MVP del partido')
            ->assertSee('Week MVP')
            ->assertSee('Estrella Local');
    }

    public function test_the_club_stats_tab_shows_the_awards(): void
    {
        $this->game->update(['mvp_player_id' => $this->homeStar->id]);

        $this->get(route('site.clubs.show', ['club' => $this->home->club_id, 'tab' => 'stats']))
            ->assertOk()
            ->assertSee('MVP DEL PARTIDO')
            ->assertSee('WEEK MVP');
    }

    // ── El panel ──────────────────────────────────────────────────────────

    public function test_the_administrator_picks_the_week_mvp_from_the_matchday_form(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(EditMatchday::class, ['record' => $this->matchday->getKey()])
            ->fillForm(['week_mvp_player_id' => $this->awayStar->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($this->awayStar->id, $this->matchday->fresh()->week_mvp_player_id);
    }

    public function test_the_matchday_form_saves_without_a_week_mvp(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(EditMatchday::class, ['record' => $this->matchday->getKey()])
            ->fillForm(['week_mvp_player_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($this->matchday->fresh()->week_mvp_player_id);
    }

    public function test_the_administrator_picks_the_game_mvp_from_the_game_form(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(EditGame::class, ['record' => $this->game->getKey()])
            ->fillForm(['mvp_player_id' => $this->homeStar->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($this->homeStar->id, $this->game->fresh()->mvp_player_id);
    }

    public function test_the_game_form_only_offers_players_of_its_two_teams(): void
    {
        $this->player($this->team('Gamma FC'), 'Ajeno Total');
        $this->actingAs(User::factory()->create());

        $options = Livewire::test(EditGame::class, ['record' => $this->game->getKey()])
            ->assertFormFieldExists('mvp_player_id')
            ->instance()->form->getComponent('mvp_player_id')->getOptions();

        $this->assertContains('Estrella Local · Alfa FC', $options);
        $this->assertContains('Estrella Visita · Beta FC', $options);
        $this->assertNotContains('Ajeno Total · Gamma FC', $options);
    }
}
