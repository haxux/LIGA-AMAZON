# Delta for league-data-model

## MODIFIED Requirements

### Requirement: Six domain tables exist with the specified columns and constraints

The system MUST create the following tables (all with `id` and `timestamps()`):

| Table | Columns | Constraints |
|---|---|---|
| seasons | name, start_date, end_date, is_current (boolean, default false) | `name` unique |
| teams | season_id (FK→seasons, cascade), division_id (FK→divisions, nullable, restrict), name, short_name, crest_path (nullable), founded_year (nullable) | unique(season_id, name) |
| players | team_id (FK→teams, cascade), name, position, birth_date (nullable), shirt_number | unique(team_id, shirt_number) |
| stadiums | team_id (FK→teams, cascade, unique), name, city, capacity (nullable) | 1:1 with team |
| matchdays | season_id (FK→seasons, cascade), number, date (nullable, nominal) | unique(season_id, number) |
| games | matchday_id (FK→matchdays, cascade), home_team_id/away_team_id (FK→teams, restrict), kickoff_at (nullable), home_score/away_score (nullable) | indexed FKs |

`seasons` MUST have an `is_current` boolean flag (default false) identifying the active season for public display. `teams` are per-season — the system MUST NOT model a cross-season club identity.

(Previously: this requirement stated "`seasons` MUST NOT have an `is_current`/active-season flag." This change supersedes that sentence — `is_current` is now required. `teams.division_id` is also introduced here as a new nullable column; see the Divisions requirement below.)

#### Scenario: Composite unique rejects duplicate within same season

- GIVEN a season already has a team named "River"
- WHEN the same season creates another team named "River"
- THEN the insert is rejected by the `(season_id, name)` unique constraint

#### Scenario: Same team name allowed across different seasons

- GIVEN season A has a team named "River"
- WHEN season B creates a team also named "River"
- THEN both inserts succeed

#### Scenario: Stadium is strictly one-to-one with team

- GIVEN team X already has a stadium
- WHEN a second stadium is created for team X
- THEN the insert is rejected by the unique `team_id` constraint

## ADDED Requirements

### Requirement: Single-current season invariant

The system MUST enforce that at most one season has `is_current = true` at any time, via a `Season::booted()` `saving` guard (mirroring `Game::booted()`'s established pattern) that unsets `is_current` on every other season when a season is saved with `is_current = true`. This MUST be a model-level guard, not a database constraint.

#### Scenario: Marking a season current unsets all others

- GIVEN two seasons exist, one already marked `is_current = true`
- WHEN a different season is saved with `is_current = true`
- THEN the previously-current season's `is_current` becomes false
- AND only the newly-saved season has `is_current = true`

#### Scenario: Zero current seasons is a valid state

- GIVEN no season has ever been marked current
- WHEN the seasons table is queried
- THEN no row has `is_current = true`, and no error occurs

### Requirement: Divisions group teams within a season

The system MUST create a `divisions` table (`name`, `season_id` FK→seasons `cascadeOnDelete`, `unique(season_id, name)`). `teams.division_id` MUST be a nullable FK→divisions with `restrictOnDelete()`, allowing a team to remain unassigned indefinitely.

#### Scenario: Division deletion blocked while teams are assigned

- GIVEN a division with at least one team assigned via `division_id`
- WHEN a delete is attempted on that division
- THEN the database rejects it via the restrict constraint

#### Scenario: Season delete cascades to its divisions

- GIVEN a season with one or more divisions
- WHEN the season is deleted
- THEN its divisions are removed along with it

### Requirement: Game events record per-player goal/assist occurrences

The system MUST create a `game_events` table (`game_id` FK→games `cascadeOnDelete`, `player_id` FK→players `cascadeOnDelete`, `type` string [PHP-level constants, not a DB enum], `minute` nullable integer).

#### Scenario: Game deletion cascades to its events

- GIVEN a game with recorded goal/assist events
- WHEN the game is deleted
- THEN all of its `game_events` rows are removed

#### Scenario: Player deletion cascades to their events

- GIVEN a player with recorded goal/assist events
- WHEN the player is deleted
- THEN all of their `game_events` rows are removed

### Requirement: News is a standalone entity optionally tagged to a team

The system MUST create a `news` table (`title`, `slug` unique, `body` text, `published_at` nullable datetime, `cover_path` nullable, `team_id` nullable FK→teams `nullOnDelete()`). Deleting a tagged team MUST NOT delete or block deletion of its tagged news items.

#### Scenario: Deleting a tagged team nulls the news item's team reference

- GIVEN a news item tagged with `team_id` set to an existing team
- WHEN that team is deleted
- THEN the news item persists and its `team_id` becomes null

#### Scenario: News item can exist untagged

- GIVEN a news item created with `team_id` null
- WHEN it is read back
- THEN it persists with no team association and no error occurs

### Requirement: Demo seed creates two divisions for the seeded season

`DatabaseSeeder` MUST create a `divisions` row named "Primera" containing all previously-seeded teams, and a second `divisions` row named "Segunda" with zero teams assigned.

#### Scenario: Fresh seed produces populated and empty divisions

- GIVEN a clean database
- WHEN `php artisan migrate:fresh --seed` runs
- THEN "Primera" exists with all seeded teams assigned via `division_id`
- AND "Segunda" exists with zero teams assigned to it
