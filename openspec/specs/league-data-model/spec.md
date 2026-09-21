# League Data Model Specification

## Purpose

Persisted schema, Eloquent relationships, and referential-integrity rules for the league domain (Season, Team, Player, Stadium, Matchday, Game), plus the demo seed data needed to exercise both played and unplayed states before Fase 3 (admin CRUD) builds on it.

## Requirements

### Requirement: Six domain tables exist with the specified columns and constraints

The system MUST create the following tables (all with `id` and `timestamps()`):

| Table | Columns | Constraints |
|---|---|---|
| seasons | name, start_date, end_date, is_current (boolean, default false) | `name` unique |
| clubs | name, short_name, crest_path (nullable), founded_year (nullable) | `name` unique |
| teams | season_id (FK→seasons, cascade), division_id (FK→divisions, nullable, restrict), club_id (FK→clubs, restrict) | unique(season_id, club_id) |
| squad_memberships | team_id (FK→teams, cascade), player_id (FK→players, cascade), shirt_number, type (owned/loan) | unique(team_id, shirt_number), unique(team_id, player_id) |
| trophies | club_id (FK→clubs, cascade), season_id (FK→seasons, cascade), name | unique(club_id, season_id, name) |
| lineups | team_id (FK→teams, cascade, unique), formation | 1:1 with team |
| lineup_slots | lineup_id (FK→lineups, cascade), player_id (FK→players, cascade), slot | unique(lineup_id, slot), unique(lineup_id, player_id) |
| players | club_id (FK→clubs, cascade), name, position, specific_position (nullable), market_value (nullable), left_at/left_to (nullable), birth_date (nullable) | — |
| budget_movements | club_id (FK→clubs, cascade), season_id (FK→seasons, cascade), transfer_id (FK→transfers, nullable, cascade), type (ingreso/egreso), amount, reason, status, created_by (FK→users, nullable) | indexed (club_id, status) |
| transfers | player_id (FK→players, cascade), season_id (FK→seasons, cascade), type, scope, from_club_id/to_club_id (FK→clubs, nullable), external_club (nullable), fee, loan_term (nullable) | indexed (season_id, type) |
| conversations | — | one per pair of participants |
| conversation_user | conversation_id (FK→conversations, cascade), user_id (FK→users, cascade), last_read_at (nullable) | unique(conversation_id, user_id) |
| messages | conversation_id (FK→conversations, cascade), user_id (FK→users, cascade), body | indexed (conversation_id, id) |
| offers | conversation_id (FK→conversations, cascade), player_id (FK→players, cascade), from_club_id (FK→clubs, cascade), amount, status, moved_by (FK→users, nullable), transfer_id (FK→transfers, nullable) | indexed (status, id) |
| stadiums | club_id (FK→clubs, cascade, unique), name, city, capacity (nullable) | 1:1 with club |
| matchdays | season_id (FK→seasons, cascade), division_id (FK→divisions, cascade), number, date (nullable, nominal) | unique(season_id, division_id, number) |
| games | matchday_id (FK→matchdays, cascade), home_team_id/away_team_id (FK→teams, restrict), kickoff_at (nullable), home_score/away_score (nullable) | indexed FKs |
| standing_zones | division_id (FK→divisions, cascade), label, color (palette key), from_position, to_position | unique(division_id, label) |

`seasons` MUST have an `is_current` boolean flag (default false) identifying the active season for public display. `teams` are per-season — the system MUST NOT model a cross-season club identity.

(Previously: this requirement stated "`seasons` MUST NOT have an `is_current`/active-season flag." This change (Fase 5) supersedes that sentence — `is_current` is now required. `teams.division_id` is also introduced here as a new nullable column; see the Divisions requirement below.)

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

For `Team→Season`, `Player→Team`, `Stadium→Team`, `Matchday→Season`, `Matchday→Division`, and `Game→Matchday`, the system MUST cascade deletes to dependents. For `Game.home_team_id` and `Game.away_team_id`, the system MUST `restrictOnDelete()`.

#### Scenario: Season delete cascades to all dependents

- GIVEN a season with teams, matchdays, players, stadiums, and games
- WHEN the season is deleted
- THEN all of its teams, matchdays, players, stadiums, and games are removed

#### Scenario: Team delete blocked while referenced by a game

- GIVEN a team that is the home or away team of at least one game
- WHEN a delete is attempted on that team
- THEN the database rejects it via the restrict constraint

### Requirement: Eloquent relationships resolve bidirectionally

All FK links above MUST be exposed as Eloquent relationships (e.g. `Season::teams()`, `Team::season()`, `Team::players()`, `Team::stadium()`, `Season::matchdays()`, `Division::matchdays()`, `Matchday::division()`, `Matchday::games()`, `Game::homeTeam()`, `Game::awayTeam()`), configured using the codebase's established attribute-based convention (`#[Fillable]`, `#[Hidden]`, `casts()` — see `app/Models/User.php`), not the classic `protected $fillable`/`protected $casts` properties.

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

## ADDED Requirements (Fase 5)

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

### Requirement: A club exists across seasons; a team is its entry in one

The system MUST model the club — name, short name, crest and founding year — as an entity of
its own, and `teams` MUST be that club's entry in one season and division. A club MUST NOT be
able to enter the same season twice.

Reading a team's identity MUST keep working through the team (`$team->name` and friends
delegate to the club), so that the views, services and tests written before this change need
no edit; writing it MUST happen on the club.

A player MUST belong to a club, and where they play each season — with which shirt number and
on what terms, owned or loaned — MUST be a separate membership record. The shirt number
belongs to the membership because it changes from season to season while the club does not. A
stadium MUST belong to the club for the same reason.

Enrolling a club in a new season MUST inherit its most recent squad, shirt numbers included.
Loans MUST NOT be inherited: a loan ends with its season.

#### Scenario: One club, two seasons, one identity

- GIVEN a club that played 2025/26 and is enrolled in 2026/27
- WHEN its name or crest is edited
- THEN both seasons show the change, because there is one club and two entries

#### Scenario: The same shirt number in two seasons

- GIVEN a squad where a player wore number 9 last season
- WHEN another player is given number 9 this season
- THEN both records stand, while two players sharing a number within one squad are rejected

#### Scenario: A new season starts with last season's squad

- GIVEN a club whose previous season had a squad, one of them on loan
- WHEN the club is enrolled in the following season
- THEN the owned players are copied with their shirt numbers, and the loan is not

### Requirement: A player's specific position belongs to their general one

The system MUST offer 27 specific positions (POR, DFC, DCI, DCD, LI, LD, CAI, CAD, MCD, MDI,
MDD, MC, MCI, MCID, MI, MD, MCO, MOI, MOD, SD, SDI, SDD, EI, ED, DC, DI, DD), each belonging
to exactly one general position, and MUST reject a pairing that crosses them — a goalkeeper
cannot be a winger. The public team page groups players by the general position, so a
mismatch would misfile them.

It MUST remain optional: the players already in the league have none, and assigning them is
the coach's job.

#### Scenario: The options follow the general position

- GIVEN a goalkeeper
- WHEN a specific position is chosen for them
- THEN POR is the only option offered, and anything else is rejected on save

### Requirement: A trophy belongs to a club, and a starting eleven to one season's team

Trophies MUST hang off the club, with the season they were won in recorded: a title is won
once and displayed in every season that follows, which is what the club entity exists for. The
same club MUST NOT be awarded the same trophy twice in one season.

A starting eleven MUST belong to the team — the club's entry in one season — because an
eleven describes that season's squad and no other. It MUST store slots, numbered 1 to 11
across the formation's lines with the goalkeeper first, and never coordinates: pixels would
tie the record to the pitch as drawn today.

The formation MUST come from a closed list of eight — 4-4-2, 4-3-3, 4-2-3-1, 4-1-4-1, 4-5-1,
3-5-2, 3-4-3 and 5-3-2 — each declared as its outfield lines, which is what lets the pitch be
drawn from the stored slot alone. A formation outside the list MUST be rejected on save, by
the model and not only by the form, as with every other vocabulary here.

#### Scenario: A trophy outlives the season it was won in

- GIVEN a club that won a cup in a past season
- WHEN a new season starts
- THEN the trophy is still listed for that club

### Requirement: A club's balance is derived from its ledger, never stored

The system MUST hold a club's money as a book of movements — club, season, type, amount,
reason, status and who created it — and MUST derive the balance as the club's initial balance
plus approved income minus approved expense. It MUST NOT store a balance: the reason is the
one that already governs the league table (ARQUITECTURA.md §3), that a stored total silently
disagrees with its source the first time a row is corrected or rejected.

An amount MUST be positive, and the type MUST carry the direction: an expense stored as a
negative number would give two ways to say the same thing, and the first mistaken sum would
go unnoticed.

A movement MUST count towards the balance only once approved. A coach creates proposals and
an administrator approves or rejects them; what is proposed or rejected MUST be kept and MUST
NOT move the balance.

The balance MUST carry across seasons — a club's money does not reset in August. The season on
each movement is what the ledger is filtered by.

#### Scenario: A proposal moves nothing until it is approved

- GIVEN a club whose balance is its initial figure
- WHEN its coach proposes an expense
- THEN the balance is unchanged, and it drops only once an administrator approves it

### Requirement: A transfer is one row, executed as it is saved

A transfer MUST record the player, the season, its type (fichaje, venta, préstamo), its scope
(inside or outside the league), the club at each end and, when one end is outside, that club's
name as free text. Saving it MUST execute it, in one transaction:

1. **Money.** A fee MUST generate the expense in the buying club and the income in the selling
   club, both already approved — this money is not proposed by anyone, it happens. A loan MUST
   generate none: a loan has no cost.
2. **Squad.** The player MUST leave the selling club's squad for that season and join the
   buying club's, taking the first free shirt number. A signing MUST change the owning club; a
   loan MUST NOT, and its membership MUST be a loan.
3. **Leaving the league.** A sale outside the league MUST mark the player as gone, with the
   date and the club's name, and MUST NOT delete them: their `game_events` cascade from that
   row, and deleting it would rewrite past seasons' scorers and cards.

A transfer between two league clubs MUST be recorded once, as the buyer's signing, and a sale
between league clubs MUST be refused as such: two rows is how a club ends up having sold a
player nobody bought. The same row MUST read as a signing from one side and a sale from the
other.

A loan's term MUST be recorded as data and MUST NOT expire on its own: this deployment runs no
scheduled tasks.

#### Scenario: One signing, two budgets

- GIVEN two league clubs and a player in the first one's squad
- WHEN a signing is recorded with a fee
- THEN the buyer is charged, the seller is credited, both movements are approved, and the
  player is in the buyer's squad and owned by them

#### Scenario: A loan costs nothing

- GIVEN a loan between two league clubs
- WHEN it is recorded
- THEN no movement is created, the player joins as a loan and keeps their owning club

### Requirement: A player carries one value, not one per season

`players.market_value` MUST be optional and MUST NOT be negative. It MUST be a single value
rather than one per season, so that a squad's total is what the squad is worth now and not
what it cost. Only an administrator sets it.

#### Scenario: A squad total follows today's values

- GIVEN a squad whose players carry values
- WHEN the club page is opened
- THEN the total is the sum of their current values

### Requirement: A conversation is one per pair, and an offer is a state machine inside it

There MUST be at most one conversation between any two people: two threads between the same
two would split the history and leave the unread counter meaningless. What each participant
has read MUST be a timestamp on their participation, not a flag per message — the question it
answers is how many are left, which a date answers.

An offer MUST record the conversation it was made in, the player, the club bidding, the
amount, its status (enviada, aceptada, rechazada, negociando, ejecutada) and who moved it last.
The amount MUST be positive, and a club MUST NOT bid for a player it already owns: an offer is
for someone in the other party's squad.

Accepting an offer MUST NOT move squads or budgets (design D10). It MUST mark the offer agreed
and leave it for an administrator, who executes it by recording a transfer — the single write
that pays, collects and moves a player. Two paths writing the same thing is how one of them
ends up unwatched.

Negotiating MUST leave the previous offer as `negociando` and create a counter-offer for the
same player and the same buying club, so the chain reads whole in the thread. Whoever moved an
offer MUST NOT be the one to answer it, which is what keeps a negotiation alternating.

#### Scenario: Accepting agrees and nothing else

- GIVEN an offer for a player of another club
- WHEN the other coach accepts it
- THEN it reads as accepted, no movement or transfer exists, and the player has not moved

#### Scenario: A counter-offer keeps the buyer

- GIVEN an offer from club A for a player of club B
- WHEN B's coach counters with a higher amount
- THEN the first offer reads as negotiating and the new one still has A as the buying club

### Requirement: Standings zones band a division's table by position

The system MUST store promotion, relegation and European bands as positions on a division —
never as team ids — because the table itself is derived live and holds no persisted rows.
Colours MUST come from a closed palette declared on `StandingZone`, so a band can never be
given a value that disappears against the table's surface, and restyling the site cannot mean
rewriting rows.

Two invariants MUST be enforced at model level: a band's last position cannot sit above its
first, and two bands of one division cannot cover the same position. Bands of different
divisions are independent.

#### Scenario: Overlapping bands are refused

- GIVEN a division with a band covering positions 1 to 3
- WHEN a second band covering positions 3 to 6 is saved
- THEN it is rejected, naming the band that already holds position 3

#### Scenario: Division deletion cascades to its bands

- GIVEN a division with standings zones
- WHEN the division is deleted
- THEN its `standing_zones` rows are removed

### Requirement: Game events record per-player occurrences

The system MUST create a `game_events` table (`game_id` FK→games `cascadeOnDelete`, `player_id` FK→players `cascadeOnDelete`, `type` string [PHP-level constants, not a DB enum], `minute` nullable integer). The vocabulary — goal, assist, yellow card, red card, clean sheet — MUST live on `GameEvent`, and widening it MUST NOT require a migration. `Player::POSITIONS` MUST live on the model for the same reason: the clean-sheet invariant needs the goalkeeper position, and `app/Models` MUST NOT read it from `app/Filament`.

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
