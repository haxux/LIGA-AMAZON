<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameReminder;
use App\Models\Matchday;
use App\Services\GameSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El cron diario: anular lo vencido y avisar de lo próximo (ver
 * CronController, que es lo único externo que dispara esto).
 */
class GameSchedulingServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameSchedulingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GameSchedulingService::class);
    }

    public function test_an_overdue_unplayed_game_is_voided_as_a_scoreless_draw(): void
    {
        $game = Game::factory()->create(['kickoff_at' => now()->subDays(2)]);

        $voided = $this->service->voidOverdueGames();

        $this->assertSame(1, $voided);
        $game->refresh();
        $this->assertSame(0, $game->home_score);
        $this->assertSame(0, $game->away_score);
        $this->assertNotNull($game->voided_at);
    }

    public function test_a_game_still_inside_the_grace_period_is_not_voided_yet(): void
    {
        Game::factory()->create(['kickoff_at' => now()->subHours(2)]);

        $this->assertSame(0, $this->service->voidOverdueGames());
    }

    public function test_an_already_played_game_is_left_alone(): void
    {
        $game = Game::factory()->create(['kickoff_at' => now()->subDays(2), 'home_score' => 2, 'away_score' => 1]);

        $this->assertSame(0, $this->service->voidOverdueGames());
        $this->assertNull($game->fresh()->voided_at);
    }

    public function test_a_game_without_a_kickoff_date_is_never_voided(): void
    {
        Game::factory()->create(['kickoff_at' => null]);

        $this->assertSame(0, $this->service->voidOverdueGames());
    }

    public function test_sends_exactly_one_reminder_per_threshold(): void
    {
        // Un solo Matchday para los tres: el factory por omisión acuña una
        // División nueva por Season nueva, y DivisionFactory sólo tiene dos
        // valores únicos ("Primera"/"Segunda") que agotar.
        $matchday = Matchday::factory()->create();

        $in3Days = Game::factory()->for($matchday)->create(['kickoff_at' => now()->addDays(3)->setTime(18, 0)]);
        $today = Game::factory()->for($matchday)->create(['kickoff_at' => now()->setTime(20, 0)]);
        $farAway = Game::factory()->for($matchday)->create(['kickoff_at' => now()->addDays(10)]);

        $sent = $this->service->sendReminders();

        $this->assertSame(2, $sent);
        $this->assertSame(1, GameReminder::query()->where('game_id', $in3Days->id)->count());
        $this->assertSame(GameReminder::KIND_3_DAYS, GameReminder::query()->where('game_id', $in3Days->id)->value('kind'));
        $this->assertSame(GameReminder::KIND_KICKOFF, GameReminder::query()->where('game_id', $today->id)->value('kind'));
        $this->assertSame(0, GameReminder::query()->where('game_id', $farAway->id)->count());
    }

    public function test_running_reminders_twice_the_same_day_does_not_duplicate(): void
    {
        $game = Game::factory()->create(['kickoff_at' => now()->addDay()]);

        $this->service->sendReminders();
        $secondRun = $this->service->sendReminders();

        $this->assertSame(0, $secondRun);
        $this->assertSame(1, GameReminder::query()->where('game_id', $game->id)->count());
    }

    public function test_an_already_played_game_gets_no_reminder(): void
    {
        Game::factory()->create(['kickoff_at' => now()->addDay(), 'home_score' => 1, 'away_score' => 0]);

        $this->assertSame(0, $this->service->sendReminders());
    }
}
