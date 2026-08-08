# League Data Model Specification

## Purpose

Persisted schema, Eloquent relationships, and referential-integrity rules for the league domain (Season, Team, Player, Stadium, Matchday, Game), plus the demo seed data needed to exercise both played and unplayed states before Fase 3 (admin CRUD) builds on it.

## Requirements

### Requirement: Six domain tables exist with the specified columns and constraints

The system MUST create the following tables (all with `id` and `timestamps()`):

| Table | Columns | Constraints |
|---|---|---|
| seasons | name, start_date, end_date | `name` unique |
| teams | season_id (FK→seasons, cascade), name, short_name, crest_path (nullable), founded_year (nullable) | unique(season_id, name) |
| players | team_id (FK→teams, cascade), name, position, birth_date (nullable), shirt_number | unique(team_id, shirt_number) |
| stadiums | team_id (FK→teams, cascade, unique), name, city, capacity (nullable) | 1:1 with team |
| matchdays | season_id (FK→seasons, cascade), number, date (nullable, nominal) | unique(season_id, number) |
| games | matchday_id (FK→matchdays, cascade), home_team_id/away_team_id (FK→teams, restrict), kickoff_at (nullable), home_score/away_score (nullable) | indexed FKs |

`seasons` MUST NOT have an `is_current`/active-season flag. `teams` are per-season — the system MUST NOT model a cross-season club identity.

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

### Requirement: Game scores are nullable and represent "not played"

`Game.home_score` and `Game.away_score` MUST be nullable unsigned integers, with no separate status/enum column. Both null MUST mean the game has not been played; both set MUST mean it has. `Matchday.date` is nominal/label only — `Game.kickoff_at` is the authoritative kickoff time.

#### Scenario: Unplayed game has null scores

- GIVEN a game created with `home_score` and `away_score` both null
- WHEN it is read back
- THEN both scores are null and the game is treated as not played

#### Scenario: Played game has both scores set

- GIVEN a concluded game
- WHEN it is scored 2-1
- THEN both `home_score` and `away_score` persist as non-null integers

### Requirement: Game home and away teams must differ

The system MUST reject saving a `Game` where `home_team_id` equals `away_team_id`. This MUST be enforced by an Eloquent model-level guard, not a raw SQL CHECK constraint.

#### Scenario: Model rejects identical home/away team

- GIVEN a game is being saved with `home_team_id` equal to `away_team_id`
- WHEN save is attempted
- THEN application-level validation rejects it before the database is touched

### Requirement: Delete strategy protects Game history

For `Team→Season`, `Player→Team`, `Stadium→Team`, `Matchday→Season`, and `Game→Matchday`, the system MUST cascade deletes to dependents. For `Game.home_team_id` and `Game.away_team_id`, the system MUST `restrictOnDelete()`.

#### Scenario: Season delete cascades to all dependents

- GIVEN a season with teams, matchdays, players, stadiums, and games
- WHEN the season is deleted
- THEN all of its teams, matchdays, players, stadiums, and games are removed

#### Scenario: Team delete blocked while referenced by a game

- GIVEN a team that is the home or away team of at least one game
- WHEN a delete is attempted on that team
- THEN the database rejects it via the restrict constraint

### Requirement: Eloquent relationships resolve bidirectionally

All FK links above MUST be exposed as Eloquent relationships (e.g. `Season::teams()`, `Team::season()`, `Team::players()`, `Team::stadium()`, `Season::matchdays()`, `Matchday::games()`, `Game::homeTeam()`, `Game::awayTeam()`), configured using the codebase's established attribute-based convention (`#[Fillable]`, `#[Hidden]`, `casts()` — see `app/Models/User.php`), not the classic `protected $fillable`/`protected $casts` properties.

#### Scenario: Relationships traverse in both directions

- GIVEN a fully seeded season
- WHEN each relationship method is called in both parent→child and child→parent directions
- THEN each call returns the correct related record(s)

### Requirement: Demo seed produces mixed played/unplayed state

`DatabaseSeeder` MUST run in FK order (Season → Team → Stadium → Player → Matchday → Game) and populate one division's worth of teams with a complete matchday calendar for a season "in progress": earlier matchdays scored, later matchdays left unplayed (null scores). Seeded `Team.crest_path` MUST be left null.

#### Scenario: Fresh seed yields both states with no FK errors

- GIVEN a clean database
- WHEN `php artisan migrate:fresh --seed` runs
- THEN some games have non-null scores, later games have null scores, no team has a `crest_path`, and no foreign key error occurs
