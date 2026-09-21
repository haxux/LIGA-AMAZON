<?php

namespace Tests\Feature;

use App\Models\Cup;
use App\Models\CupRound;
use App\Models\CupTeam;
use App\Models\CupTie;
use App\Models\Division;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Services\ClubSeasonService;
use App\Services\Competition;
use App\Services\GoalscorersService;
use App\Services\PlayerStatsService;
use App\Services\ScorerRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las estadísticas separadas por competición (Fase 15, decisión del
 * propietario: «separados por competición»).
 *
 * Además de separar, esto arregla un agujero: hasta ahora la temporada de un
 * partido se preguntaba por su jornada, y un partido de copa NO tiene jornada,
 * así que sus goles y sus tarjetas no aparecían en ninguna parte.
 */
class CompetitionStatsTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Division $division;

    private Cup $cup;

    private Team $team;

    private Team $rival;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->division = Division::factory()->create(['season_id' => $this->season->id]);
        $this->cup = Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa Amazonas']);

        $this->team = $this->teamNamed('Manaos FC');
        $this->rival = $this->teamNamed('Tapajós SC');

        foreach ([$this->team, $this->rival] as $team) {
            CupTeam::create(['cup_id' => $this->cup->id, 'team_id' => $team->id]);
        }

        $this->player = Player::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Duarte',
            'position' => 'Forward',
        ]);
    }

    private function teamNamed(string $name): Team
    {
        return Team::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
            'name' => $name,
        ]);
    }

    private function leagueGame(int $for, int $against, int $number = 1): Game
    {
        $matchday = Matchday::factory()->create([
            'season_id' => $this->season->id,
            'division_id' => $this->division->id,
            'number' => $number,
        ]);

        return Game::factory()->for($matchday)->create([
            'home_team_id' => $this->team->id,
            'away_team_id' => $this->rival->id,
            'home_score' => $for,
            'away_score' => $against,
        ]);
    }

    private function cupGame(int $for, int $against): Game
    {
        $round = CupRound::create([
            'cup_id' => $this->cup->id,
            'name' => 'Final',
            'position' => 1,
            'legs' => 1,
        ]);

        $tie = CupTie::create([
            'cup_round_id' => $round->id,
            'home_team_id' => $this->team->id,
            'away_team_id' => $this->rival->id,
        ]);

        return Game::factory()->create([
            'matchday_id' => null,
            'cup_tie_id' => $tie->id,
            'home_team_id' => $this->team->id,
            'away_team_id' => $this->rival->id,
            'home_score' => $for,
            'away_score' => $against,
        ]);
    }

    /**
     * La regresión que esto arregla: sin jornada, un gol de copa no llegaba a
     * ninguna clasificación de goleadores.
     */
    public function test_a_cup_goal_counts_in_the_season_leaderboard(): void
    {
        GameEvent::factory()->for($this->cupGame(2, 0))->for($this->player)->goal()->create();

        $rows = app(GoalscorersService::class)->topScorers($this->season);
        $row = $rows->firstWhere(fn (ScorerRow $r) => $r->player->is($this->player));

        $this->assertNotNull($row, 'Un gol de copa tiene que contar en el total de la temporada.');
        $this->assertSame(1, $row->count);
    }

    public function test_the_leaderboard_splits_league_from_cup(): void
    {
        $league = $this->leagueGame(3, 1);
        $cup = $this->cupGame(1, 0);

        GameEvent::factory()->count(3)->for($league)->for($this->player)->goal()->create();
        GameEvent::factory()->for($cup)->for($this->player)->goal()->create();

        $goalscorers = app(GoalscorersService::class);
        $count = fn (Competition $competition) => $goalscorers
            ->topScorers($this->season, 10, null, $competition)
            ->firstWhere(fn (ScorerRow $r) => $r->player->is($this->player))?->count ?? 0;

        $this->assertSame(4, $count(Competition::all()));
        $this->assertSame(3, $count(Competition::league()));
        $this->assertSame(1, $count(Competition::cup($this->cup)));
    }

    /**
     * Las cifras del club iban a medias: los goles de copa ya sumaban —salen
     * del marcador— mientras que sus tarjetas no, porque se contaban por la
     * jornada. Ahora o entra todo o no entra nada, según lo elegido.
     */
    public function test_club_stats_split_league_from_cup(): void
    {
        $league = $this->leagueGame(3, 1);
        $cup = $this->cupGame(1, 2);

        GameEvent::factory()->for($league)->for($this->player)->yellowCard()->create();
        GameEvent::factory()->for($cup)->for($this->player)->yellowCard()->create();

        $service = app(ClubSeasonService::class);

        $all = $service->stats($this->team);
        $this->assertSame(2, $all->played);
        $this->assertSame(4, $all->goals_for);
        $this->assertSame(2, $all->yellow_cards);

        $liga = $service->stats($this->team, Competition::league());
        $this->assertSame(1, $liga->played);
        $this->assertSame(3, $liga->goals_for);
        $this->assertSame(1, $liga->won);
        $this->assertSame(1, $liga->yellow_cards);

        $copa = $service->stats($this->team, Competition::cup($this->cup));
        $this->assertSame(1, $copa->played);
        $this->assertSame(1, $copa->goals_for);
        $this->assertSame(1, $copa->lost);
        $this->assertSame(1, $copa->yellow_cards);
    }

    public function test_player_totals_split_by_competition(): void
    {
        GameEvent::factory()->count(2)->for($this->leagueGame(2, 0))->for($this->player)->goal()->create();
        GameEvent::factory()->for($this->cupGame(1, 0))->for($this->player)->goal()->create();

        $stats = app(PlayerStatsService::class);

        $this->assertSame(3, $stats->totals($this->player, $this->season)[GameEvent::TYPE_GOAL]);
        $this->assertSame(2, $stats->totals($this->player, $this->season, Competition::league())[GameEvent::TYPE_GOAL]);
        $this->assertSame(1, $stats->totals($this->player, $this->season, Competition::cup($this->cup))[GameEvent::TYPE_GOAL]);
    }

    /**
     * Ofrecer una copa que el club no juega es ofrecer una pantalla de ceros,
     * el mismo criterio que la ficha usa con las temporadas.
     */
    public function test_a_team_is_only_offered_the_cups_it_plays(): void
    {
        $otra = Cup::factory()->create(['season_id' => $this->season->id, 'name' => 'Copa del Río']);

        $keys = Competition::forTeam($this->team)->pluck('key')->all();

        $this->assertContains('copa:'.$this->cup->id, $keys);
        $this->assertNotContains('copa:'.$otra->id, $keys);
        $this->assertSame([Competition::ALL, Competition::LEAGUE], array_slice($keys, 0, 2));
    }

    public function test_an_invented_competition_falls_back_to_everything(): void
    {
        $options = Competition::forSeason($this->season);

        $this->assertTrue(Competition::resolve($options, 'copa:99999')->isAll());
        $this->assertTrue(Competition::resolve($options, null)->isAll());
    }

    public function test_the_statistics_page_filters_by_competition(): void
    {
        GameEvent::factory()->for($this->leagueGame(1, 0))->for($this->player)->goal()->create();

        $otro = Player::factory()->create(['team_id' => $this->rival->id, 'name' => 'Brandão']);
        GameEvent::factory()->for($this->cupGame(0, 1))->for($otro)->goal()->create();

        $this->get('/estadisticas')->assertOk()->assertSee('Duarte')->assertSee('Brandão');

        $this->get('/estadisticas?competicion='.Competition::LEAGUE)
            ->assertOk()
            ->assertSee('Duarte')
            ->assertDontSee('Brandão');

        $this->get('/estadisticas?competicion=copa:'.$this->cup->id)
            ->assertOk()
            ->assertSee('Brandão')
            ->assertDontSee('Duarte');
    }

    /**
     * Los puntos sólo se cuentan donde hay tabla: en una copa no significan
     * nada, y en «Todo» sumarían partidos que no dan puntos.
     */
    public function test_the_club_stats_tab_filters_and_only_the_league_shows_points(): void
    {
        $this->leagueGame(3, 1);
        $this->cupGame(1, 2);

        $url = '/equipos/'.$this->team->club_id.'/stats?temporada='.$this->season->id;

        $this->get($url.'&competicion='.Competition::LEAGUE)->assertOk()->assertSee('PTS');
        $this->get($url.'&competicion=copa:'.$this->cup->id)->assertOk()->assertDontSee('PTS');
        $this->get($url)->assertOk()->assertDontSee('PTS');
    }

    /**
     * Un club que no juega ninguna copa no tiene filtro que elegir, y su
     * pestaña tiene que quedar exactamente como estaba: con sus puntos.
     */
    public function test_a_club_without_a_cup_keeps_its_points(): void
    {
        $solo = $this->teamNamed('Xingu Rangers');

        $this->get('/equipos/'.$solo->club_id.'/stats?temporada='.$this->season->id)
            ->assertOk()
            ->assertSee('PTS')
            // El rótulo suelto no vale: el pie del sitio también lo lleva.
            ->assertDontSee('name="competicion"', false);
    }
}
