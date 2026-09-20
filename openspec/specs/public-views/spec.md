# public-views Specification

## Purpose

Public-facing, unauthenticated Blade views and their composition rules: standings (division-aware), partidos (combined schedule/results), goleadores (season-wide top scorers/assisters), and noticias (listing + slug detail). A read-only surface over the Fase 2–5 domain models and services.

## Requirements

### Requirement: Active season resolution with fallback

Every public page that requires a season MUST resolve it as: the season with `is_current = true`; if none is marked current, the most recently created season (`Season::latest('id')->first()`); if zero seasons exist, respond with HTTP 404.

#### Scenario: Active season resolves to the flagged season

- GIVEN one season has `is_current = true` among several seasons
- WHEN a public page resolves the active season
- THEN it uses the `is_current` season

#### Scenario: Fallback to most recently created season

- GIVEN no season has `is_current = true`
- WHEN a public page resolves the active season
- THEN it uses the most recently created season by id

#### Scenario: Zero seasons yields 404

- GIVEN no seasons exist in the database
- WHEN any public page requiring a season is requested
- THEN the response is HTTP 404

### Requirement: Standings page renders one table per non-empty division

The standings page (`/`) MUST render one standings table per division belonging to the selected season with at least one team assigned, using `StandingsService::forDivision()` per division. A division with zero teams MUST render nothing — no placeholder.

The page MUST carry the same `?temporada` filter as partidos: it defaults to the active season and falls back to it when the value is unknown. Both pages MUST share one filter-bar component, submitted as a plain GET form.

Each table MUST paint the standings zones of its division: a colour bar on the position cell of every row the zone covers, and a legend under the table listing each zone's label and colour. A division with no zones MUST render neither bar nor legend — the table carries no default bands of its own.

#### Scenario: A zone paints its rows and appears in the legend

- GIVEN a division whose first two positions belong to an "Ascenso" zone
- WHEN the standings page is requested
- THEN the first two rows carry that zone's colour, the third does not, and the legend lists "Ascenso" in that colour

#### Scenario: Divisions with teams each render a table

- GIVEN the active season has a "Primera" division with 10 teams and a "Segunda" division with 0 teams
- WHEN the standings page is requested
- THEN a "Primera" table renders with 10 rows
- AND no "Segunda" table or placeholder appears

#### Scenario: Standings page reflects an is_current change

- GIVEN season A is `is_current` and season B is not
- WHEN season B is marked `is_current` instead, and the standings page is requested again
- THEN it renders season B's divisions and teams, not season A's

### Requirement: Partidos page groups games by division and matchday with fixture/result branching

The partidos page (`/partidos`) MUST render games grouped by division and, within each division, by matchday. Each game MUST render as a result card when both `home_score` and `away_score` are non-null, or as a fixture card when both are null. No separate calendar/results route or status field MUST be used to decide this.

The page MUST offer three filters, submitted as a plain GET form so each selection is a shareable URL: `temporada` (defaulting to the active season), `division` (defaulting to `todas`, i.e. every division as its own section), and `jornada` (defaulting to the matchday being played now, or `todas` for the whole calendar). A filter value that does not exist in the current selection — including one left stale by a season change — MUST fall back to that filter's default instead of erroring.

`MatchdayResolver` MUST define "the matchday being played now" as the earliest matchday whose date is today or later; once every date has passed, the most recent one; and, when no matchday carries a date, the lowest-numbered one.

#### Scenario: The page opens on the current jornada of every division

- GIVEN a season whose divisions have matchdays before, on, and after today
- WHEN the partidos page is requested with no filters
- THEN each division renders its own section showing only the matchday dated today or next

#### Scenario: A stale filter falls back instead of erroring

- GIVEN a URL carrying a division or jornada that the selected season does not have
- WHEN the partidos page is requested
- THEN the page responds 200 and applies that filter's default

#### Scenario: Scored matchday renders result cards

- GIVEN a matchday whose games all have both scores set
- WHEN the partidos page is requested
- THEN each of that matchday's games renders as a result card showing both scores

#### Scenario: Unplayed matchday renders fixture cards

- GIVEN a matchday whose games have both scores null
- WHEN the partidos page is requested
- THEN each of that matchday's games renders as a fixture card with no scores shown

#### Scenario: Games are grouped by their matchday

- GIVEN the active season has multiple matchdays
- WHEN the partidos page is requested
- THEN games are grouped under their own matchday, in matchday order

### Requirement: Goleadores page ranks top 10 scorers and top 10 assisters season-wide

The goleadores page MUST resolve the active season, then use `GoalscorersService` to compute the top 10 players by goal-type `game_events` count and the top 10 players by assist-type `game_events` count, both season-wide (not scoped by division).

#### Scenario: Top scorers and assisters both render

- GIVEN `game_events` exist across multiple players and teams in the active season
- WHEN the goleadores page is requested
- THEN a top-10 scorers list and a top-10 assisters list both render, each ordered by descending event count

#### Scenario: Player with zero events is absent from both lists

- GIVEN a player with no `game_events` in the active season
- WHEN the goleadores page is requested
- THEN that player does not appear in either list

#### Scenario: Fewer than 10 scoring players still renders correctly

- GIVEN only 3 players in the active season have any goal events
- WHEN the goleadores page is requested
- THEN the scorers list shows exactly those 3 players, with no placeholder rows for the missing 7

### Requirement: Noticias listing shows only published items in descending order

The noticias listing page MUST show only news items where `published_at` is non-null and `published_at <= now()`, ordered by `published_at` descending. Items with a null or future `published_at` MUST NOT appear.

#### Scenario: Only published items appear, most recent first

- GIVEN 3 news items with past `published_at` values and 1 with a null `published_at`
- WHEN the noticias listing page is requested
- THEN only the 3 published items appear, ordered most-recent-first
- AND the draft item does not appear

#### Scenario: Future-dated news is hidden until its time passes

- GIVEN a news item with `published_at` set to a future timestamp
- WHEN the noticias listing page is requested before that timestamp
- THEN the item does not appear in the listing

### Requirement: Noticias detail page resolves a single item by slug

A slug-based detail route MUST render a single news item's full content when its `slug` matches AND it is currently published (`published_at` non-null and `<= now()`). An unpublished or non-existent slug MUST respond with HTTP 404.

#### Scenario: Published item is viewable by its slug

- GIVEN a published news item with slug "gran-victoria"
- WHEN its detail page is requested at that slug
- THEN the full item (title, body, cover, published_at) renders with a 200 response

#### Scenario: Unpublished item is not publicly viewable by slug

- GIVEN a news item with a null or future `published_at` and slug "borrador"
- WHEN its detail page is requested at that slug
- THEN the response is HTTP 404

### Requirement: The coach's way in does not change the public site

The public site MUST offer a way for a coach to sign in, and a signed-in coach MUST see their
club's crest and name in the top right of every public page, linking to their panel.

For everyone else the page MUST be exactly what it was: a visitor with no session, and an
administrator, MUST see no crest. No public view may require a session to render.

#### Scenario: A visitor sees the site unchanged

- GIVEN no session
- WHEN any public page is requested
- THEN no club crest appears, and the sign-in entry is the only addition

#### Scenario: A signed-in coach carries their club

- GIVEN a coach with a session
- WHEN any public page is requested
- THEN their club's crest and name appear in the header, linking to their panel

### Requirement: Team crest images render from uploaded crest_path with fallback

Public pages that display a team identity (standings table rows, game cards) MUST render
the team's real crest image from `crest_path` via the public disk when it is set, and MUST
render a generic fallback placeholder — not a broken image or an empty element — when
`crest_path` is null.

#### Scenario: Team with an uploaded crest shows its real image

- GIVEN a team has `crest_path` set to an uploaded file
- WHEN that team appears in a standings table row or a game card
- THEN the real crest image renders using that team's `crest_path`

#### Scenario: Team without a crest shows a generic fallback

- GIVEN a team has `crest_path` null
- WHEN that team appears in a standings table row or a game card
- THEN a generic fallback placeholder renders in place of the crest image
