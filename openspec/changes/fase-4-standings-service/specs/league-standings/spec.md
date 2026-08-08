# League Standings Specification

## Purpose

Derived (non-persisted) league table computation for a single season: per-team played/won/drawn/lost/goals/points, ordering, and the played-game definition, as delivered by `StandingsService::forSeason(Season $season)`.

## Requirements

### Requirement: Per-team aggregate stats are computed from played games only

The system MUST compute, for each team scoped to the given season, `played`, `won`, `drawn`, `lost`, `goals_for`, `goals_against`, `goal_difference`, and `points` (3 for a win, 1 for a draw, 0 for a loss, no deductions or bonuses), derived solely from games where BOTH `home_score` and `away_score` are non-null.

#### Scenario: Correct stats for a team with played games

- GIVEN a team has played 3 games with known scores: one win, one draw, one loss
- WHEN standings are computed for the season
- THEN the team's row shows `played=3`, `won=1`, `drawn=1`, `lost=1`, `goals_for`/`goals_against` summed from those 3 games, `goal_difference = goals_for - goals_against`, and `points = 3*won + 1*drawn`

#### Scenario: Home and away games both fold into the same team's stats

- GIVEN a team has played one game as home team (scoring X, conceding Y) and one game as away team (scoring P, conceding Q)
- WHEN standings are computed for the season
- THEN the team's `goals_for = X + P`, `goals_against = Y + Q`, and `played = 2`, with win/draw/loss and points derived correctly from both games regardless of home/away role

### Requirement: A game counts as played only when both scores are set

The system MUST treat a game as "not played" — and exclude it from every counter (`played`, `won`, `drawn`, `lost`, `goals_for`, `goals_against`) — unless both `home_score` and `away_score` are non-null. A game with exactly one score set MUST be treated identically to a game with both scores null.

#### Scenario: Fully unplayed game is excluded

- GIVEN a game with `home_score` and `away_score` both null
- WHEN standings are computed for the season containing that game
- THEN the game contributes nothing to either team's counters

#### Scenario: Half-entered game is excluded

- GIVEN a game with `home_score` set to a value and `away_score` still null (or vice versa)
- WHEN standings are computed for the season containing that game
- THEN the game contributes nothing to either team's counters, identically to a fully unplayed game

### Requirement: Standings are ordered by points, then goal difference, then goals for

The system MUST order the returned rows by `points` descending; ties MUST be broken by `goal_difference` descending; further ties MUST be broken by `goals_for` descending. No other tie-break (head-to-head, alphabetical, etc.) is applied.

#### Scenario: Multi-level tie-break ordering

- GIVEN three teams where two have equal points, and among those two one has a higher goal difference, and a third pair (not among the first two) has equal points AND equal goal difference but differs in goals for
- WHEN standings are computed for the season
- THEN teams are ordered by points descending; among equal-points teams, the higher goal-difference team ranks first; among teams equal on both points and goal difference, the higher goals-for team ranks first

### Requirement: Standings are scoped to a single season via matchday, including zero-game teams

The system MUST scope the computation to games whose `matchday.season_id` matches the given season, MUST NOT include games belonging to a different season's matchday, and MUST include every team belonging to the given season even if it has zero played games, represented as an all-zero row (`played=0`, `won=0`, `drawn=0`, `lost=0`, `goals_for=0`, `goals_against=0`, `goal_difference=0`, `points=0`).

#### Scenario: Cross-season games are excluded

- GIVEN a season A and a season B, each with their own matchdays and games
- WHEN standings are computed for season A
- THEN only games whose matchday belongs to season A contribute to the result; games under season B's matchdays have no effect on season A's table

#### Scenario: Zero-game team appears as an all-zero row

- GIVEN a team belongs to the given season but has no games with both scores set
- WHEN standings are computed for that season
- THEN the team appears in the result with an all-zero row rather than being omitted
