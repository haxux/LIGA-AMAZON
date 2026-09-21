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

### Requirement: Matchdays and their games are scoped to a division

`MatchdayResource`'s form MUST require a division, offering only the divisions of the season selected on the same form, and MUST clear the selected division when the season changes. Matchday numbers MUST be unique per (season, division), not per season. `MatchdaysTable` MUST show the division and MUST offer a division filter.

Both team Selects on the Game form (in `GameResource` and in the `MatchdayResource` relation manager alike) MUST offer only the teams of the matchday's division, and MUST reject a team from another division as a form error. `GameResource` MUST clear both team selections when the matchday changes. This MUST stay at the form layer: `StandingsService` remains specified for games whose teams sit in different divisions.

#### Scenario: Division options follow the chosen season

- GIVEN two seasons, each with its own divisions
- WHEN an operator picks one season on the matchday form
- THEN only that season's divisions are offered, and picking the other season clears the choice

#### Scenario: Two divisions each run a Jornada 1

- GIVEN a season with a Primera and a Segunda division
- WHEN an operator creates a matchday numbered 1 in each
- THEN both are accepted, while a second Jornada 1 in the same division is rejected as a form error

#### Scenario: Only the division's clubs can play its jornada

- GIVEN a matchday in the Primera division
- WHEN an operator opens its games, in either the resource or the relation manager
- THEN only Primera clubs are offered, and submitting a Segunda club is rejected as a form error

### Requirement: Standings zones are edited from their division

`DivisionResource` MUST expose a `StandingZonesRelationManager` for defining that division's
bands: a label shown in the public legend, a colour picked from `StandingZone::COLORS`, and
the first and last positions covered. An inverted range and an overlap with a sibling band
MUST both surface as field errors rather than as an exception escaping the model guard, and a
band being edited MUST NOT count as overlapping itself.

#### Scenario: A band is defined from the division page

- GIVEN an operator editing a division
- WHEN they add a band labelled "Descenso" over the last two positions
- THEN it is persisted against that division and appears in the public table's legend

#### Scenario: A band cannot be widened over its neighbour

- GIVEN a division with bands over positions 1–3 and 4–6
- WHEN the operator widens the first band to position 5
- THEN the form rejects it, naming the band that already holds those positions

### Requirement: Trophies are awarded from the admin panel only

`TrophyResource` MUST let the administrator award a trophy by club, season and name, and
MUST reject the same award twice for one club and season as a form error. The coach's panel
MUST show their club's trophies read-only, with no create, edit or delete: a palmarés each
coach could write would not be one.

#### Scenario: The coach reads what the administrator awarded

- GIVEN a club with trophies and another club with its own
- WHEN its coach opens their trophies module
- THEN they see only their club's, and no way to add one

### Requirement: The administrator answers proposals and records transfers

`BudgetMovementResource` MUST list every club's ledger, let the administrator record a movement
of their own — which is born approved, since they have nobody to ask — and answer a coach's
proposal by approving or rejecting it. Approving and rejecting MUST be actions rather than an
edit of the status field: what the administrator decides about a proposal is an act, not
another form field. The number of proposals awaiting an answer MUST be visible on the
navigation entry, which is the administrator's tray.

`TransferResource` MUST record transfers and MUST NOT offer editing one, because saving a
transfer executes it and editing it afterwards would move the money twice or not at all.
Deleting one MUST remove the budget movements it created, and the confirmation MUST say that
the player is not moved back.

A club's initial balance MUST be set from `ClubResource`, and a player's value from the
player's identity form.

#### Scenario: Rejecting keeps the record

- GIVEN a movement proposed by a coach
- WHEN the administrator rejects it
- THEN it is kept in the ledger as rejected and the balance does not move

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

## ADDED Requirements (Fase 5)

### Requirement: SeasonResource exposes an is_current toggle

`SeasonResource`'s form MUST include a `Toggle` field bound to `is_current`, and its table MUST include an `IconColumn` rendering `is_current` as a boolean, sortable.

#### Scenario: Toggling is_current on unsets other seasons inline

- GIVEN two seasons exist, one currently marked `is_current`
- WHEN an operator toggles `is_current` on for the other season and saves
- THEN the save succeeds and the previously-current season's toggle reflects false on the next list view

#### Scenario: is_current column is visible and sortable in the list

- GIVEN multiple seasons exist with varying `is_current` values
- WHEN an operator views the Season list
- THEN each row shows a boolean icon for `is_current` and the column can be sorted

### Requirement: News has a standalone CRUD Resource

The system MUST provide `NewsResource`, reachable from panel navigation, offering full create/read/update/delete for News, including a `FileUpload` field for `cover_path` bound to the `public` disk under `news/`, plus fields for `title`, `slug`, `body`, `published_at`, and an optional team select.

#### Scenario: Operator creates a news item with an image

- GIVEN an operator is on `NewsResource`'s create page
- WHEN they fill title, slug, body, upload a cover image, and save
- THEN the news item persists with `cover_path` pointing to a file under `news/`

#### Scenario: Operator leaves published_at empty to keep a draft

- GIVEN an operator is creating a news item
- WHEN they leave `published_at` empty and save
- THEN the item persists with `published_at` null

### Requirement: Divisions have a standalone CRUD Resource, and Team gains a division field

The system MUST provide `DivisionResource`, reachable from panel navigation, offering full create/read/update/delete for Division, scoped by season. `TeamResource`'s form MUST include a `Select` field for `division_id`, allowing null.

#### Scenario: Operator creates a division for a season

- GIVEN an operator is on `DivisionResource`'s create page
- WHEN they select a season, enter a unique name, and save
- THEN the division is persisted and linked to that season

#### Scenario: Operator assigns a team to a division

- GIVEN an operator is editing a Team
- WHEN they select a division from the `division_id` field and save
- THEN the team's `division_id` is updated

#### Scenario: Operator leaves a team without a division

- GIVEN an operator is editing a Team with `division_id` null
- WHEN they save without selecting a division
- THEN the team persists with `division_id` null

### Requirement: GameResource gains a GameEventsRelationManager

The system MUST expose a `GameEventsRelationManager` on `GameResource`'s edit/view page for recording `game_events`, with a player `Select` scoped to the two teams participating in that game (resolved via the relation manager's owner record, since a relation manager's own schema has no `home_team_id`/`away_team_id` field for `GameForm`'s `Get`-based pattern to read).

The recordable types MUST be goal, assist, yellow card, red card and clean sheet. For a clean sheet the player `Select` MUST narrow to goalkeepers, and the minute input MUST be hidden — a clean sheet is the whole game, not a moment in it. Changing the type MUST clear an already-picked player when, and only when, the clean-sheet boundary is crossed, so switching between goal and assist keeps the operator's choice.

`GameEvent` MUST reject a clean sheet recorded for an outfield player and MUST drop any minute submitted with one, at model level: the `Select` covers the UI path only.

#### Scenario: A card is recorded against any player on the pitch

- GIVEN a game between two teams
- WHEN an operator records a yellow or red card for a player of either team, with a minute
- THEN a `game_events` row is persisted with that type, player and minute

#### Scenario: A clean sheet only reaches a goalkeeper

- GIVEN a game whose squads hold goalkeepers and outfield players
- WHEN an operator selects the clean-sheet type
- THEN only goalkeepers are offered, no minute is asked for, and a submission naming an outfield player is rejected as a form error

#### Scenario: Operator records a goal for a player in the game

- GIVEN an operator is editing a Game
- WHEN they open the Game Events relation manager, select a player from one of the two participating teams, set type to goal, and save
- THEN a `game_events` row is persisted linked to that game and player

#### Scenario: Player select is scoped to the game's two teams

- GIVEN a Game between Team A and Team B
- WHEN the operator opens the player `Select` in the Game Events relation manager
- THEN only players belonging to Team A or Team B are selectable
