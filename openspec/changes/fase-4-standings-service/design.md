# Design: Fase 4 — StandingsService

## Technical Approach

Exploration's Approach 3, finalized. `StandingsService::forSeason(Season $season)` runs **two**
queries — the season's team roster (the row universe) and the season's *played* games (the score
source) — folds both home and away perspectives of each game into a mutable per-team accumulator,
materializes each accumulator as an immutable `StandingRow`, and sorts points → GD → GF. Nothing is
written, cached, or persisted (`ARQUITECTURA.md` §3: *"se deriva, no se guarda como fuente de
verdad"*).

This is the codebase's **first** `App\Services\` class, so it also sets the layer's conventions.
`config.yaml` `rules.design` reserves the Services layer for business logic — standings arithmetic
qualifies; Fase 3 correctly declined it for CRUD.

## Architecture Decisions

### D1 — Return shape: `Collection<int, StandingRow>` of readonly value objects

| Option | Tradeoff | Verdict |
|---|---|---|
| Plain `array`/`Collection` of assoc arrays | Zero new files; but untyped, typo-silent (`$row['goals_fors']` → `null`), and `points`/`goal_difference` become just two more forgeable keys | Rejected |
| Eloquent model backed by a view/query | Requires a table or DB view — contradicts locked "not persisted" | Rejected |
| **`final class StandingRow` with public readonly props** | One new file; typed, IDE-navigable, `points`/`goal_difference` computed **in the constructor** so they cannot disagree with the counters they derive from | **Chosen** |

The codebase has no plain-array precedent to preserve: every existing domain object is a typed class,
and the models use the modern attribute convention (`#[Fillable]`, `casts()`, typed relation return
types) rather than loose arrays. A readonly VO is the consistent extension of that taste. It also
survives contact with Blade unchanged (`$row->points`), and `data_get()` reads its public props, so
`Collection::sortBy` works on it directly (D4).

**Property naming is snake_case** (`goals_for`, `goal_difference`), not camelCase: it matches the
spec's vocabulary verbatim, matches how every Eloquent attribute in this app already reads in Blade
(`$team->short_name`), and lets the sort keys be literally the spec's field names.

**No `position`/`rank` property.** Rank is the 1-based index of the ordered collection; Blade uses
`$loop->iteration`. Storing it would force post-sort mutation of a readonly object for zero gain.

The row carries the whole `Team` model (not just id+name) so Fase 5 can render `crest_path`,
`short_name`, etc. without a second lookup. The teams are already hydrated by the roster query.

### D2 — Class shape: stateless, no constructor, instance method (not static)

```php
namespace App\Services;

final class StandingsService
{
    public function forSeason(Season $season): Collection { /* ... */ }
}
```

No constructor arguments: the service's only collaborators are Eloquent statics, which are globally
resolvable — there is nothing to inject and a constructor would be ceremony. **Not static**, though:
an instance method keeps the class container-resolvable, so a Fase 5 controller can simply type-hint
`StandingsService $standings` in its action and Laravel auto-wires the zero-arg class. A static
method would foreclose swapping/decorating it later (e.g. a caching decorator, explicitly out of
scope now but cheap to keep possible). No service-provider binding is needed — zero-arg concrete
classes are auto-resolved.

### D3 — Two queries, not one; roster seeds the accumulator

The zero-game-team requirement makes a games-only accumulator impossible: a team with no played
games appears in **no** game row. So the row universe comes from the roster query.

```php
$rows = Team::query()
    ->where('season_id', $season->getKey())
    ->orderBy('id')                       // deterministic seed order — see D4
    ->get()
    ->mapWithKeys(fn (Team $team) => [$team->getKey() => [
        'team' => $team, 'played' => 0, 'won' => 0, 'drawn' => 0,
        'lost' => 0, 'goals_for' => 0, 'goals_against' => 0,
    ]])->all();

$games = Game::query()
    ->whereNotNull('home_score')
    ->whereNotNull('away_score')
    ->whereHas('matchday', fn (Builder $q) => $q->where('season_id', $season->getKey()))
    ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

foreach ($games as $game) {
    $this->accumulate($rows, $game->home_team_id, $game->home_score, $game->away_score);
    $this->accumulate($rows, $game->away_team_id, $game->away_score, $game->home_score);
}
```

```php
private function accumulate(array &$rows, int $teamId, int $for, int $against): void
{
    if (! isset($rows[$teamId])) {
        return; // team is not on this season's roster — see D5
    }

    $rows[$teamId]['played']++;
    $rows[$teamId]['goals_for'] += $for;
    $rows[$teamId]['goals_against'] += $against;

    if ($for > $against)      { $rows[$teamId]['won']++; }
    elseif ($for < $against)  { $rows[$teamId]['lost']++; }
    else                      { $rows[$teamId]['drawn']++; }
}
```

The single `accumulate()` call used twice with swapped arguments **is** the home/away symmetry — one
code path, so the away side cannot drift from the home side.

- `whereHas` (correlated `EXISTS` subquery) beats `whereIn(matchday_ids)`: one round trip, no
  unbounded id list. Rejected the SQL `GROUP BY … UNION` aggregate (exploration #2) — opaque and
  untestable in isolation at ~380 games.
- **No `->with(['homeTeam','awayTeam'])`**: the roster query already hydrated every team we can
  attribute to, so eager-loading them again would be a redundant query. Column selection keeps the
  hydrated `Game` models to exactly the four fields the fold reads.
- Both `whereNotNull` clauses together implement "played" — a half-entered result is filtered by the
  database, never by PHP.

### D4 — Sorting, and what a full tie does

```php
return collect($rows)
    ->map(fn (array $row) => new StandingRow(...$row))
    ->sortBy([['points', 'desc'], ['goal_difference', 'desc'], ['goals_for', 'desc']])
    ->values();
```

Verified against the installed framework: `Collection::sortBy()` with an array of comparisons
delegates to `sortByMany()` (`vendor/laravel/framework/src/Illuminate/Collections/Collection.php`
:1588-1685), which runs one `uasort` applying comparisons in order and stopping at the first
non-zero — exactly the three-level tie-break, in one pass. **`uasort` is stable as of PHP 8.0**, and
this project requires `php: ^8.3`, so no stable-sort workaround is needed.

Use the **explicit nested form** shown above rather than `sortByDesc(['points', 'goal_difference'])`:
a flat two-element string array can be misread as a `[class, method]` callable by `sortBy`'s
`is_callable` check. Three nested arrays are unambiguous.

Full tie (points, GD **and** GF all equal): stable sort preserves insertion order, which is the
roster query's `orderBy('id')` — deterministic and test-reproducible, per state.yaml's "arbitrary but
stable" allowance. `orderBy('id')` deliberately, **not** `orderBy('name')`: alphabetical ordering is
an explicitly rejected tie-break, and seeding by name would smuggle it back in.

`->values()` reindexes so the caller gets a 0-based list, not team-id keys.

### D5 — Teams outside the roster are ignored, not invented

Exploration's unenforced assumption: nothing in the schema forces a game's teams to belong to its
matchday's season. If a season-A game references a team whose `season_id` is B, the `isset()` guard
in `accumulate()` drops that side while the in-roster opponent still gets its stats. Rationale: the
table's roster is *"teams belonging to the given season"* (spec Requirement 4); silently minting a
row for a foreign team would put a season-B club in season A's table. This state is unreachable
through Filament (Fase 3 scopes team Selects) — the guard is defense in depth, not an expected path.

## Data Flow

```
Season ──┬─► Team::where(season_id)         ──► accumulator seed  (all-zero rows)
         │
         └─► Game::whereHas(matchday.season_id)
             ->whereNotNull(home_score, away_score)
                        │
                        ├─ home perspective ─┐
                        └─ away perspective ─┴─► accumulate() ──► StandingRow[]
                                                                       │
                                              sortBy(pts↓, gd↓, gf↓) ──┴──► Collection
```

## File Changes

| File | Action | Description |
|---|---|---|
| `app/Services/StandingsService.php` | Create | The service (D2/D3/D4). First file in a new `app/Services/` directory. |
| `app/Services/StandingRow.php` | Create | Readonly row VO (D1). |
| `tests/Feature/StandingsServiceTest.php` | Create | Constructed-fixture tests (Testing Strategy). |

Nothing is modified. No migration, no model change, no route, no Filament file, no config edit.

**`StandingRow` lives in `App\Services`, not `App\DTOs`.** It is the service's return contract and its
only consumer today; a second top-level namespace for one class is premature, and `ARQUITECTURA.md`
names the Services layer only. If DTOs ever proliferate, moving it is a namespace-only refactor with
no behavior risk.

## Interfaces / Contracts

```php
namespace App\Services;

final class StandingRow
{
    public readonly int $goal_difference;
    public readonly int $points;

    public function __construct(
        public readonly Team $team,
        public readonly int $played = 0,
        public readonly int $won = 0,
        public readonly int $drawn = 0,
        public readonly int $lost = 0,
        public readonly int $goals_for = 0,
        public readonly int $goals_against = 0,
    ) {
        $this->goal_difference = $this->goals_for - $this->goals_against;
        $this->points = ($this->won * 3) + $this->drawn;   // 3/1/0, no deductions
    }
}
```

`points` and `goal_difference` are **derived in the constructor**, not passed in — they cannot be set
inconsistently with the counters, and the 3/1/0 rule exists in exactly one place. Defaults of `0`
make an all-zero row literally `new StandingRow(team: $team)`.

```php
final class StandingsService
{
    /** @return Collection<int, StandingRow> ordered points desc, GD desc, GF desc */
    public function forSeason(Season $season): Collection;   // Illuminate\Support\Collection
}
```

`forSeason()` is the **sole** public method. `accumulate()` is `private`.

## Testing Strategy (`strict_tdd: true`)

Runner is **PHPUnit 12** (no Pest installed), SQLite `:memory:`, `RefreshDatabase` — matching every
existing test. All rows below live in **one** file, `tests/Feature/StandingsServiceTest.php`.

**Placement note (divergence from `proposal.md`):** the proposal said `tests/Unit/`. It must be
`tests/Feature/` — `tests/Unit/` extends bare `PHPUnit\Framework\TestCase` (no app boot, no DB,
see `tests/Unit/ExampleTest.php`), and this service reads through Eloquent. Fase 2 made the same
call for its "unit" rows.

Fixtures are **hand-constructed** with explicit scores. `GameFactory::played()` randomizes scores
(`fake()->numberBetween(0, 5)`) and is unusable for exact assertions — a private
`playGame(Matchday $md, Team $home, Team $away, int $hs, int $as)` helper builds them.

| # | Spec scenario | Test method | Assertion |
|---|---|---|---|
| 1 | R1 / *Correct stats for a team with played games* | `test_team_with_win_draw_loss_has_correct_row` | 3 constructed games (W/D/L) → `played=3, won=1, drawn=1, lost=1`, GF/GA summed, `goal_difference` = GF−GA, `points=4` |
| 2 | R1 / *Home and away games both fold* | `test_home_and_away_games_fold_into_the_same_team` | Team plays 1 home + 1 away → `played=2`, GF/GA summed across both roles, W/D/L correct regardless of role |
| 3 | R2 / *Fully unplayed game is excluded* | `test_game_with_both_scores_null_is_excluded` | Both scores null → both teams stay all-zero |
| 4 | R2 / *Half-entered game is excluded* | `test_game_with_only_one_score_set_is_excluded` | `home_score=2, away_score=null` **and** the mirror case → both teams all-zero, identical to #3 |
| 5 | R3 / *Multi-level tie-break ordering* | `test_rows_are_ordered_by_points_then_goal_difference_then_goals_for` | Constructed table where pair A/B tie on points but differ on GD, and pair C/D tie on points **and** GD but differ on GF → assert exact team-name order of the whole collection |
| 6 | R4 / *Cross-season games are excluded* | `test_games_from_another_season_do_not_affect_this_seasons_table` | Seasons A and B each with matchdays+games → season A's table is unchanged by season B's results; row count = A's team count |
| 7 | R4 / *Zero-game team appears as all-zero row* | `test_team_with_no_played_games_appears_as_an_all_zero_row` | Team present in the collection with every counter `0` |

Supplementary rows (design-level guarantees, not spec scenarios):

| # | Target | Test method | Assertion |
|---|---|---|---|
| 8 | D1 contract | `test_row_exposes_derived_points_and_goal_difference` | `StandingRow` returns `StandingRow` instances; `points`/`goal_difference` match the 3/1/0 and GF−GA formulas |
| 9 | D4 stability | `test_fully_tied_teams_keep_a_stable_order` | Two teams identical on points/GD/GF → order matches roster id order, and repeated calls return the same order |
| 10 | D5 defense | `test_team_outside_the_season_roster_is_not_added_to_the_table` | Game under season A's matchday referencing a season-B team → no extra row; the season-A opponent still gets its stats |
| 11 | Reconciliation | `test_demo_seed_produces_a_consistent_table` | On `DatabaseSeeder` data: Σ`played` = 2 × played-game count; Σ`points` = 3×(decisive games) + 2×(draws); Σ`goals_for` = Σ`goals_against` |
| 12 | Regression | existing Fase 2/3 suite | `php artisan test` fully green |

**Note on the spec count:** the delta spec at
`specs/league-standings/spec.md` contains **7** scenarios across its 4 requirements, not the 9
stated in the design brief. All 7 are mapped above (rows 1-7); rows 8-11 add the four design-level
guarantees that would otherwise be untested.

**400-line budget risk: Low.** Two small classes (~120 lines) plus one test file (~250 lines). One
PR, one slice — no chaining needed despite `delivery_strategy=auto-chain`.

## Migration / Rollout

No migration required. Purely additive and read-only: no schema, no data, no config, no route.
Rollback = delete `app/Services/` and `tests/Feature/StandingsServiceTest.php`; nothing else
references them.

## Future-Proofing Check

Per project convention (`feedback_schema_extensibility`, established in Fase 2's design).

- **Fase 5 (public Blade views)** — not foreclosed, and this is the primary consumer. A thin
  controller type-hints `StandingsService $standings`, calls `$standings->forSeason($season)`, and
  the Blade view does `@foreach ($rows as $row)` with `$loop->iteration` as the position,
  `$row->team->name` / `$row->team->crest_path` for the badge cell, and `$row->points` etc. for the
  numbers. A `Collection` of objects is exactly what Blade iterates most naturally — no `data_get`,
  no array-key typos, no view-side arithmetic (GD and points arrive precomputed).
- **A Filament standings widget (deferred)** — a widget can call the same method and feed
  `->map(fn (StandingRow $r) => …)` into a table. Nothing here is Blade- or HTTP-coupled.
- **Caching, if the table ever gets slow** — the stateless instance method is decoratable: bind an
  interface or a cached subclass in a service provider without touching callers. A `static` method
  would have blocked this; D2 is why it is not static.
- **Point deductions / sanctions (out of scope)** — would become one more input to
  `StandingRow`'s constructor (`$adjustment`) and one term in the `points` expression. Because
  `points` is derived in exactly one place, that stays a single-line change.
- **Divisions (deferred since Fase 2)** — a future `teams.division` column turns the roster query
  into an extra `where`, and `forSeason()` gains a sibling `forDivision()`; the fold and sort are
  untouched.
- **Head-to-head tie-break (explicitly rejected)** — would need the games kept, not discarded, after
  the fold. This design drops `$games` once folded. Noted deliberately: adding H2H later means
  restructuring `forSeason()` to retain per-pair results, not a one-line insert. Accepted, since
  state.yaml locks the tie-break at GD → GF.

**Watch-item carried forward**: the "played" definition now lives in two places — the DB `whereNotNull`
pair here, and Fase 2's nullable-score convention. If a `games.status` enum is ever introduced, this
query is the second site that must change, not just the schema.

## Open Questions

- [x] **Resolved (D1)**: return shape is `Collection<int, StandingRow>` of readonly value objects,
  not assoc arrays.
- [x] **Resolved (D2)**: stateless `final class`, no constructor args, instance (not static) method,
  no service-provider binding.
- [x] **Resolved (D3)**: team universe is `Team::where('season_id', …)` — zero-game teams get
  all-zero rows; games alone cannot seed the accumulator.
- [x] **Resolved (D4)**: full ties fall back to roster id order via PHP 8's stable `uasort`; no
  workaround needed.
- [x] **Resolved**: tests go in `tests/Feature/`, not `tests/Unit/` (DB access required).
