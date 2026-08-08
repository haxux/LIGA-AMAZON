<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\Team;
use Database\Factories\TeamFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Mutes Game::booted()'s saving guard (design D3). This seeder never
     * relies on that guard — distinct home/away teams are guaranteed by
     * construction via the circle-method double round-robin below.
     */
    use WithoutModelEvents;

    private const PLAYERS_PER_TEAM = 18;

    private const SCORED_MATCHDAYS = 10;

    /**
     * Seed the application's database.
     *
     * FK order: Season → Team → Stadium → Player → Matchday → Game.
     */
    public function run(): void
    {
        $season = Season::factory()->create(['name' => '2025/26']);

        $teams = collect(TeamFactory::CLUBS)
            ->map(fn (array $club, string $name) => Team::factory()->create([
                'season_id' => $season->id,
                'name' => $name,
                'short_name' => $club['short'],
                'crest_path' => null,
            ]))
            ->values();

        $teams->each(function (Team $team) {
            Stadium::factory()->create(['team_id' => $team->id]);
            Player::factory()->count(self::PLAYERS_PER_TEAM)->create(['team_id' => $team->id]);
        });

        $fixtures = $this->circleMethodDoubleRoundRobin($teams->pluck('id')->all());

        foreach ($fixtures as $matchdayIndex => $pairings) {
            $matchdayNumber = $matchdayIndex + 1;

            $matchday = Matchday::factory()->create([
                'season_id' => $season->id,
                'number' => $matchdayNumber,
                'date' => now()->addWeeks($matchdayIndex),
            ]);

            $played = $matchdayNumber <= self::SCORED_MATCHDAYS;

            foreach ($pairings as [$homeTeamId, $awayTeamId]) {
                Game::factory()
                    ->when($played, fn ($factory) => $factory->played())
                    ->create([
                        'matchday_id' => $matchday->id,
                        'home_team_id' => $homeTeamId,
                        'away_team_id' => $awayTeamId,
                        'kickoff_at' => $matchday->date,
                    ]);
            }
        }
    }

    /**
     * Circle-method double round-robin: for N teams (N even), N-1 rounds of
     * N/2 pairings each produce a single round-robin; the return leg repeats
     * every pairing with home/away swapped, for 2*(N-1) rounds total.
     *
     * 10 teams → 9 first-leg rounds + 9 second-leg rounds = 18 rounds
     * (matchdays) × 5 pairings (games) each = 90 games. Home and away are
     * always two different array elements, so distinct teams are guaranteed
     * by construction — this is what makes it safe to seed under
     * WithoutModelEvents (D3), which mutes the Game::booted() guard.
     *
     * @param  array<int, int>  $teamIds
     * @return array<int, array<int, array{0: int, 1: int}>>
     */
    private function circleMethodDoubleRoundRobin(array $teamIds): array
    {
        $teamCount = count($teamIds);
        $fixed = $teamIds[0];
        $rotating = array_slice($teamIds, 1);

        $firstLeg = [];

        for ($round = 0; $round < $teamCount - 1; $round++) {
            $current = array_merge([$fixed], $rotating);
            $pairings = [];

            for ($i = 0; $i < intdiv($teamCount, 2); $i++) {
                $pairings[] = [$current[$i], $current[$teamCount - 1 - $i]];
            }

            $firstLeg[] = $pairings;

            array_unshift($rotating, array_pop($rotating));
        }

        $secondLeg = array_map(
            fn (array $pairings) => array_map(fn (array $pair) => [$pair[1], $pair[0]], $pairings),
            $firstLeg
        );

        return array_merge($firstLeg, $secondLeg);
    }
}
