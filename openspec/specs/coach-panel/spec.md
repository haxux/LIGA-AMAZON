# coach-panel Specification

## Purpose

Defines what the director técnico does inside their own Filament panel at `/club`. New
capability — Fase 10 (Plantilla, Trofeos y Mis Enfrentamientos). Who may open the panel at
all, and the record policies that keep one coach out of another's club, belong to
`admin-panel`; the shape of the data belongs to `league-data-model`. This spec covers the
modules themselves.

The panel is in Spanish, unlike `/admin` (design D13): it is the coaches who use it.

## Requirements

### Requirement: The coach's panel is a fixed list of modules

The panel MUST register only the coach's own modules, and MUST NOT register any
administrator resource, for the reason given in `admin-panel`: hiding a resource inside a
shared panel leaves its route alive.

As of Fase 10 the modules are **Plantilla**, **Trofeos**, **Mis enfrentamientos** and
**Once ideal**. Contabilidad (Fase 12) and the chat (Fase 13) join them later.

Every module MUST scope its own query to the coach's club, independently of the record
policies. The policies are the second lock, not the first: a resource that forgets to scope
would list other clubs' records even where each row is then refused on open.

#### Scenario: A module lists only the coach's club

- GIVEN a coach assigned to club A and records belonging to club B
- WHEN they open any module of their panel
- THEN only club A's records are listed

### Requirement: The squad module sets where a player plays, not who they are

`SquadResource` MUST let the coach set a player's general position, from the four in
`league-data-model`, and their specific position, from the ones that belong to that general
one. It MUST NOT let the coach edit the player's identity — their name is the
administrator's to set — and MUST NOT offer creating or deleting players: squad arrivals and
departures are the administrator's, and become transfers in Fase 12.

Changing the general position MUST clear the specific one in the form, because a specific
position that belonged to the old general one is not merely stale, it is invalid: the model
guard in `league-data-model` rejects it on save.

The shirt number MUST be shown from the squad membership of the **current** season, not from
the player, because since Fase 9 the number lives in the membership and changes from one
season to the next. A player with no membership in the current season MUST still be listed,
with no number rather than an error.

#### Scenario: The specific options follow the general position

- GIVEN a goalkeeper in the coach's squad
- WHEN the coach opens them
- THEN POR is the only specific position offered

#### Scenario: The number comes from this season's squad

- GIVEN a player wearing 17 in the current season's squad
- WHEN the coach lists their squad
- THEN the row shows 17

### Requirement: The specific position is set from both panels

The specific position MUST be editable by the administrator as well as by the coach, from
the player's identity form in `/admin` — the one on `PlayerResource` and the one on the
club's players relation manager, which share their fields. Both MUST offer only the specific
positions belonging to the chosen general position, and MUST clear the specific one when the
general one changes.

It is one field on one row seen from two panels: a coach who leaves it unset must not leave
the administrator without a way to fill it in.

#### Scenario: The administrator assigns a specific position

- GIVEN a defender with no specific position
- WHEN the administrator edits them in `/admin` and picks LD
- THEN the player is saved with LD

### Requirement: Fixtures are the club's calendar, read-only

`FixtureResource` MUST list the games the coach's club plays, home and away, and MUST NOT
allow creating, editing or deleting one: results are loaded by the administrator.

A game MUST be matched to the club, not to the team, so that a game from a previous season
still belongs to the club that played it — which is what the club entity exists for.

The list MUST be filterable by season and by matchday. The matchday filter MUST filter by
**number** and MUST offer only the numbers the club actually plays: each division runs its
own calendar, so there is one "jornada 1" per division and season, and a filter over
matchday rows would offer the same number several times with nothing to tell them apart.

#### Scenario: A past season's game is still the club's

- GIVEN a club that played in a season before this one
- WHEN its coach opens Mis enfrentamientos
- THEN that game is listed, although the team row of that season is not this season's

#### Scenario: The matchday filter narrows to one number

- GIVEN the club's games across two matchdays
- WHEN the coach filters by the first one's number
- THEN only that matchday's games remain

### Requirement: Trophies are read-only to the coach

The trophies module MUST show the coach's club's trophies with no create, edit or delete
action. Who awards them, and the rule against awarding the same one twice in a season, is
specified in `admin-league-crud`.

#### Scenario: The coach cannot award themselves a trophy

- GIVEN a coach with their club's palmarés open
- WHEN they look for a way to add one
- THEN there is none

### Requirement: The starting eleven is a page, not a CRUD

The starting eleven MUST be a page inside the coach's panel rather than a resource (design
D5): eleven slots laid across the lines of a formation is not a table of rows.

It MUST offer the eight formations of `league-data-model`, draw the goalkeeper's line first
and MUST store the slot each player occupies, never coordinates (design D6).

The page MUST:

1. offer only the players in the club's squad **for the current season**, since the eleven
   belongs to that season's team;
2. keep, on a change of formation, every player whose slot still exists, and drop only those
   left without one — reshaping a 4-4-2 into a 4-3-3 must not empty the pitch;
3. save an incomplete eleven, because a coach builds it over several sittings;
4. refuse the same player in two slots;
5. say so plainly, instead of failing, when the club is not enrolled in the current season
   and there is therefore no squad to pick from.

#### Scenario: Changing formation keeps whoever still fits

- GIVEN an eleven laid out in 4-4-2
- WHEN the coach switches to a formation with fewer slots
- THEN the players whose slots survive are still placed, and the rest are cleared

#### Scenario: The same player cannot hold two slots

- GIVEN a player already placed
- WHEN the coach places them in a second slot and saves
- THEN the save is refused and the eleven is left as it was
