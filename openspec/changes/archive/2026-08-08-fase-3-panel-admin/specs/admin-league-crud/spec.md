# Admin League CRUD Specification

## Purpose

Filament v5 Resources, RelationManagers, and form/table composition providing full back-office CRUD over the six Fase 2 domain models (Season, Team, Player, Stadium, Matchday, Game), plus crest upload and inline surfacing of the `Game` home/away guard. No business logic (StandingsService), no public views, no role restriction — see `admin-panel` for access policy.

## Requirements

### Requirement: Five models have standalone CRUD Resources

The system MUST provide a standalone Filament Resource, reachable from panel navigation, offering full create/read/update/delete for `Season`, `Team`, `Player`, `Matchday`, and `Game`. `Stadium` MUST NOT have a standalone Resource or top-level nav item.

#### Scenario: Operator manages a model via its standalone resource

- GIVEN an authenticated user is on `/admin`
- WHEN they navigate to the Season, Team, Player, Matchday, or Game nav item
- THEN they can list, create, edit, and delete records of that model

#### Scenario: Stadium has no standalone nav entry

- GIVEN an authenticated user browses panel navigation
- WHEN the nav is inspected
- THEN no top-level "Stadiums" item exists

### Requirement: Stadium is managed exclusively via a TeamResource RelationManager

The system MUST expose `Stadium` create/edit/delete only through a RelationManager on `TeamResource`'s edit/view page, honoring the 1:1 team↔stadium constraint from Fase 2.

#### Scenario: Stadium managed from the team's edit page

- GIVEN an operator is editing a Team with no stadium yet
- WHEN they open the Stadium relation manager and create a stadium
- THEN the stadium is persisted and linked to that team

### Requirement: Player is reachable via both PlayerResource and a TeamResource RelationManager

The system MUST provide `PlayerResource` for league-wide search and filter by `position`, AND a Player RelationManager on `TeamResource` scoped to that team's squad.

#### Scenario: League-wide player search by position

- GIVEN players exist across multiple teams
- WHEN an operator filters `PlayerResource`'s list by `position`
- THEN only players matching that position are shown, regardless of team

#### Scenario: Squad management from a team's edit page

- GIVEN an operator is editing a Team
- WHEN they open the Player relation manager
- THEN only that team's players are listed, and a new player can be added directly to the team

### Requirement: Game is reachable via both GameResource and a MatchdayResource RelationManager

The system MUST provide `GameResource` for cross-matchday fixture lookup and correction, AND a Game RelationManager on `MatchdayResource` scoped to that matchday's games, to support entering a round's results.

#### Scenario: Enter a round's results via the matchday

- GIVEN a matchday with its games already generated
- WHEN an operator opens the Game relation manager on that matchday
- THEN they can edit each game's scores without leaving the matchday context

#### Scenario: Cross-matchday fixture lookup

- GIVEN games exist across several matchdays
- WHEN an operator searches `GameResource`'s list
- THEN matching games are found regardless of which matchday they belong to

### Requirement: Team crest upload wires to the Fase 2 public disk

`TeamResource`'s form MUST include a `FileUpload` component bound to the `public` disk, storing new files under `crests/`. Uploading a new crest MUST replace the team's `crest_path`, and the resulting file MUST be retrievable via its public URL.

#### Scenario: Uploading a crest replaces the stored path

- GIVEN a team with no crest
- WHEN an operator uploads an image via the crest FileUpload field and saves
- THEN `crest_path` is updated to the new file's path under `crests/`
- AND the image is viewable via its `/storage/crests/...` URL

### Requirement: Game form surfaces the home/away guard inline

Attempting to save a `Game` with `home_team_id` equal to `away_team_id` MUST surface as a field-level form error, not an unhandled exception or HTTP 500.

#### Scenario: Equal home/away team is rejected inline

- GIVEN an operator is filling the Game form
- WHEN they select the same team for both Home and Away and submit
- THEN the form re-renders with a visible validation message
- AND no exception page or 500 response occurs

### Requirement: Deletion behavior follows the Fase 2 FK strategy unmodified

The system MUST NOT add any guard, confirmation beyond Filament's default, or soft-delete on top of the Fase 2 cascade/restrict FK strategy. Cascading relations MUST delete their dependents through Filament's standard delete confirmation. Restricting relations (`Game.home_team_id`/`away_team_id`) MUST surface the database-level restriction as-is when deletion is attempted.

#### Scenario: Cascading delete removes dependents via standard confirmation

- GIVEN a Season with teams, matchdays, players, and games
- WHEN an operator confirms deletion of that Season
- THEN all dependent records are removed, using Filament's default delete confirmation with no added guard

#### Scenario: Restricted delete raises the underlying database error

- GIVEN a Team that is the home or away team of at least one Game
- WHEN an operator attempts to delete that Team
- THEN the deletion fails due to the `restrictOnDelete()` constraint from Fase 2
- AND no additional application-level guard intercepts it before the database
