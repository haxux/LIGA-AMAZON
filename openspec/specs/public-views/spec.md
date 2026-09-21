# public-views Specification

## Purpose

Public-facing, unauthenticated Blade views and their composition rules: standings (division-aware), partidos (combined schedule/results), goleadores (season-wide top scorers/assisters), noticias (listing + slug detail), and equipos (club listing + club profile). A read-only surface over the domain models and services.

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

### Requirement: Equipos lists a season's clubs by division

`/equipos` MUST list the clubs taking part in a season, grouped by division and ordered by
name, with the same `?temporada` contract as the other public pages: the value picks a
season, and anything the list does not hold falls back to the active one.

A team without a division MUST still be listed, under its own heading. `teams.division_id`
is nullable, so a club just enrolled may not have one yet, and hiding it would hide it
exactly when someone is looking for it.

Each entry MUST link to that club's profile, carrying the season being viewed.

#### Scenario: A club with no division still appears

- GIVEN a season with one division and a team left without one
- WHEN the listing is requested
- THEN both are listed, the second under a heading of its own

### Requirement: A club profile is the club's, and the season is a filter inside it

`/equipos/{club}` MUST render the profile of a **club** — the permanent identity of Fase 9 —
with six tabs in this order: General (the default), Partidos, Jugadores, Trofeos, Stats and
Técnico. The tab MUST live in the path, because a tab is a page that gets linked and shared,
while the season stays in `?temporada`, which is a filter. An unknown tab MUST fall back to
General rather than 404: a public URL is typed by hand.

The season selector MUST offer only the seasons the club actually played. A club not enrolled
in the season being viewed MUST still render — that is what a permanent identity is for — and
MUST say so instead of showing empty tabs.

Every figure MUST be derived at request time from `games` and `game_events`, with no
statistics table (design D12), reusing `StandingsService` for the position and
`GoalscorersService`, narrowed to the club's squad, for the scorer and assister.

The tabs MUST hold:

1. **General** — the next unplayed game, the last five results as G/E/P from this club's side,
   the position in its division's table, the club's top scorer and top assister of the season,
   and the starting eleven drawn on a pitch. That pitch is also where the club's own coach
   builds the eleven, for the current season only; `coach-panel` specifies it.
2. **Partidos** — every game of the club that season, played and unplayed, by matchday. This
   is the only place a club's calendar is listed: the coach's panel does not repeat it.
3. **Jugadores** — that season's squad, grouped by general position, with shirt number,
   specific position, age, value and loans marked, plus the squad's total value. Values are
   plain figures with thousands separators and no currency symbol: the league fixes no
   currency. A club's budget, unlike its players' values, MUST NOT be public.
4. **Trofeos** — the **whole** palmarés, not the selected season's: a title is won once and
   displayed ever after.
5. **Stats** — played, won, drawn, lost, goals for and against, goal difference, points,
   cards and clean sheets. Cards and clean sheets MUST count only players who belonged to that
   season's squad, since the events hang off the player and a loaned player played for two.
6. **Técnico** — the name of the coach's account, and nothing else about them. When the club
   has no coach, the tab MUST say so.

#### Scenario: The palmarés ignores the season selector

- GIVEN a club with a trophy won in a past season
- WHEN its Trofeos tab is opened on the current season
- THEN the past trophy is listed

#### Scenario: A rival's goals are not this club's

- GIVEN a game where a player of each club scored
- WHEN the club's General tab is opened
- THEN only its own player is named as top scorer

#### Scenario: An unknown tab opens General

- GIVEN a club profile
- WHEN a tab that does not exist is requested
- THEN General is rendered with HTTP 200

### Requirement: The chat is a page of the site, not of a panel

The chat MUST live at `/chat`, on the public site, and MUST NOT have a copy inside either
panel: a conversation needs the width of a page, and two screens for one thread drift apart.

It is the only page of the site that requires a session. The reader MUST be resolved from
**both** guards — a coach signs in through `club`, an administrator through `web` — and with
both open the coach's wins, which is the session the rest of the site already follows. Anyone
without either MUST be sent to the coach's sign-in, the way in the site already offers.

A participant MUST see only their own conversations and MUST NOT open someone else's by
typing its id — the administrator included, who in the chat is a presidente like any other.
There MUST be one conversation per pair, and the unread count MUST ride in the site's header,
on every page, since this deployment sends no mail and runs no websockets.

Offers MUST read inside the thread, in the order they happened, and MUST be offered only when
both sides are coaches with clubs: bidding needs a club to buy with, and an administrator runs
none. An offer MUST say which of the four operations it is — buy, sell, borrow or lend —
because that decides whose squad the player is picked from and whether what is agreed is a
price or a term; a loan carries no money at all. What is agreed is what gets executed: a loan
closed in the chat is recorded as a loan. `league-data-model` specifies what accepting does, and does not do.

#### Scenario: A thread that is not yours does not open

- GIVEN a conversation between two other people
- WHEN a third person requests it by id
- THEN nothing is shown

#### Scenario: The header carries what is waiting

- GIVEN two messages nobody has read yet
- WHEN their recipient opens any page of the site
- THEN the Chat entry carries the number 2

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
