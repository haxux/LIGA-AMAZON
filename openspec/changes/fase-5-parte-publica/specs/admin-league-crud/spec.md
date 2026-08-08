# Delta for admin-league-crud

## ADDED Requirements

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

The system MUST expose a `GameEventsRelationManager` on `GameResource`'s edit/view page for recording `game_events` (goals/assists), with a player `Select` scoped to the two teams participating in that game (resolved via the relation manager's owner record, since a relation manager's own schema has no `home_team_id`/`away_team_id` field for `GameForm`'s `Get`-based pattern to read).

#### Scenario: Operator records a goal for a player in the game

- GIVEN an operator is editing a Game
- WHEN they open the Game Events relation manager, select a player from one of the two participating teams, set type to goal, and save
- THEN a `game_events` row is persisted linked to that game and player

#### Scenario: Player select is scoped to the game's two teams

- GIVEN a Game between Team A and Team B
- WHEN the operator opens the player `Select` in the Game Events relation manager
- THEN only players belonging to Team A or Team B are selectable
