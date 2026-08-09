# Delta for league-standings

## ADDED Requirements

### Requirement: Standings can be computed for a division instead of a season

The system MUST provide `StandingsService::forDivision(Division $division): Collection<StandingRow>`, computing the same per-team aggregate stats (`played`, `won`, `drawn`, `lost`, `goals_for`, `goals_against`, `goal_difference`, `points`), the same played-game definition (both scores non-null), and the same ordering (points descending, then goal_difference descending, then goals_for descending) as `forSeason()`, but scoped to teams where `team.division_id` equals the given division's id, rather than to a season's full team roster. Each division team's folded games remain scoped to its season (same game universe `forSeason()` would use for that team) — the game scope is NOT further filtered by the opponent's division. `forSeason()`'s existing signature and behavior MUST remain completely unchanged by this addition.

#### Scenario: Division standings include only the division's teams

- GIVEN a division within a season containing teams X and Y, and a separate division in the same season containing team Z
- WHEN `forDivision()` is called for the first division
- THEN the result contains rows for X and Y only, not Z

#### Scenario: A division team's stats include games against teams outside its division

- GIVEN team X (in division A) played and won a game against team Z (in division B, same season)
- WHEN `forDivision()` is called for division A
- THEN team X's row reflects that game's result (points, goals) exactly as `forSeason()` would compute it for X

#### Scenario: Zero-game division team appears as an all-zero row

- GIVEN a team belongs to the division but has no played games
- WHEN `forDivision()` is called for that division
- THEN the team appears in the result with an all-zero row rather than being omitted

#### Scenario: Multi-level tie-break ordering matches forSeason()'s rules

- GIVEN two division teams tied on points where goal difference breaks the tie
- WHEN `forDivision()` computes standings
- THEN the higher goal-difference team ranks first, following the same points→goal_difference→goals_for ordering as `forSeason()`

#### Scenario: Empty division returns an empty collection

- GIVEN a division with zero teams assigned
- WHEN `forDivision()` is called for that division
- THEN an empty collection is returned, not an error

**Note**: `forSeason()`'s existing requirements and scenarios (per-team aggregation, played-game definition, ordering, season scoping including zero-game teams) are UNCHANGED by this addition and are not restated here.
