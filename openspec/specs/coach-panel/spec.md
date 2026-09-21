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

As of Fase 13 the modules are **Plantilla**, **Contabilidad** (read-only) and **Fichajes**,
which is where the coach proposes.

Four things that might be expected here are deliberately absent, because the public site
already holds them and a second screen for the same thing only adds places to look: the
**starting eleven**, which the coach builds on the club's own page, the **palmarés**, the
club's **calendar**, and the **chat**, which needs a whole page and gets one at `/chat`
(`public-views`). The panel does not link to them either — the public site is the way
back.

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
one. It MUST NOT let the coach edit the player's identity — their name is the administrator's
to set — and MUST NOT offer creating or deleting players: squad arrivals and departures are
the administrator's, and become transfers in Fase 12.

Changing the general position MUST clear the specific one in the form, because a specific
position that belonged to the old general one is not merely stale, it is invalid: the model
guard in `league-data-model` rejects it on save.

The player's value MUST be shown in the squad and MUST NOT be editable there: it is the
administrator who sets it.

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

### Requirement: The coach proposes four operations, always outside the league

What a coach proposes from his panel is the **operation**, and it always points outside the
league: what moves between league clubs is negotiated in the chat, with the other coach. The
form therefore MUST NOT ask for a scope.

The four are: **Fichaje** (buy someone from outside), **Venta** (put one of his own up for
sale), **Cesión** (borrow someone from outside) and **Ceder** (offer one of his own on loan).
Each asks only what it needs:

1. what comes in names the player as **free text**, because they have no identity in the
   league yet — the administrator creates it if the deal closes;
2. what goes out picks from his own squad;
3. fichaje and venta MUST carry an amount, since a money operation with no figure is a
   question, not a proposal;
4. cesión and ceder carry a **term** and no money at all.

His club is one end and he does not choose it, the season is the current one, the status is
proposed, and the author is him. A proposal MUST move nothing: no money, no squad, no
identity created.

Contabilidad stays **read-only** to him: a loose proposal of income or expense, with no
operation behind it, left the administrator guessing what it was for.

#### Scenario: A proposal waits, and nothing moves

- GIVEN a coach asking to sign someone from outside the league
- WHEN the proposal is stored
- THEN it reads as proposed, no player identity exists, no movement exists, and his balance is
  unchanged

#### Scenario: A loan asks for a term, not a price

- GIVEN a coach proposing to borrow a player
- WHEN the form is shown
- THEN it asks for the term and offers no amount

### Requirement: The coach reads their transfer history, both directions

The Fichajes module MUST list the transfers where the coach's club is at either end —
executed, proposed and rejected alike — filterable by season, type and status. Closing one is
the administrator's, and how he closes it is specified in `admin-league-crud`.

Whatever he decides, the coach MUST be told in his chat, naming the player and what was
agreed: the chat is the only notice this deployment has.

Each row MUST be labelled from this club's side — a signing for the club receiving the player,
a sale for the one letting them go — since the same row is both.

#### Scenario: One row, two readings

- GIVEN a transfer from club A to club B
- WHEN A's coach opens their history
- THEN the row reads as a sale, naming B as the other side

### Requirement: The starting eleven is built on the club's public page

The starting eleven MUST be edited on the club's public profile, in the General tab, and MUST
NOT have a second screen in the coach's panel: two screens writing the same lineup drift
apart, and the place to judge how an eleven looks is the place where it is shown.

The page MUST render the editor only for the coach of that club and only for the **current**
season — a past season's eleven is history, not a draft. Everyone else MUST get the pitch as
plain HTML, drawn with whatever is stored or empty, and no interactive component at all.

Because the editing requests do not travel through the public route, the component MUST
re-check that permission on **every** action, and MUST refuse a player who is not in that
season's squad even when the id is supplied by hand.

It MUST offer the eight formations of `league-data-model`, draw the goalkeeper's line first,
and MUST store the slot each player occupies, never coordinates (design D6).

The editor MUST:

1. offer only the players in the club's squad for the current season;
2. keep, on a change of formation, every player whose slot still exists, and drop only those
   left without one — reshaping a 4-4-2 into a 4-3-3 must not empty the pitch;
3. place a player who already holds another slot by **moving** them, not by repeating them;
4. save only when the coach asks it to, never on each pick: on a page anyone can read, saving
   every click would leave a half-built eleven on show;
5. save an incomplete eleven, because a coach builds it over several sittings.

#### Scenario: Only the club's own coach gets the editor

- GIVEN a club's public profile
- WHEN an anonymous visitor, another club's coach, or an administrator opens it
- THEN the eleven is drawn read-only and no save button exists

#### Scenario: Changing formation keeps whoever still fits

- GIVEN an eleven laid out in 4-4-2
- WHEN the coach switches to a formation with fewer slots
- THEN the players whose slots survive are still placed, and the rest are cleared

#### Scenario: Placing a player twice moves them

- GIVEN a player already placed in one slot
- WHEN the coach places them in another
- THEN the first slot is emptied and the player holds only the second
