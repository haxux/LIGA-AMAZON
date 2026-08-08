# Apply Progress: Fase 4 — StandingsService

> All 22 tasks complete. Apply phase done — ready for `sdd-verify`.

## Current position

- **Branch**: `fase-4/1-standings-service` (created off `fase-3/4-game` tip, single work unit, single branch — matches the `fase-2/*` / `fase-3/*` unmerged-stack pattern; not merged into `master`)
- Single work unit only (per tasks.md's Review Workload Forecast: ~370 estimated changed lines, Low budget risk, no chaining needed).

## Task status (22/22 tasks — ALL DONE)

### Phase 1: Foundation — `StandingRow` value object — DONE (1.1–1.2)

- **1.1–1.2**: RED — `test_row_exposes_derived_points_and_goal_difference` referenced `App\Services\StandingRow`, which didn't exist → `Class "App\Services\StandingRow" not found`. GREEN — created `app/Services/StandingRow.php` exactly per design D1: `final class` with public readonly props (`team`, `played`, `won`, `drawn`, `lost`, `goals_for`, `goals_against`), `goal_difference` and `points` derived in the constructor (`goals_for - goals_against`, `won*3 + drawn`). Triangulated with a second test (`test_row_defaults_to_an_all_zero_row_when_no_counters_are_given`) proving the all-defaults path (`new StandingRow(team: $team)`) yields every counter at `0` — this is the exact shape D3's roster seed uses.

### Phase 2: Core skeleton — roster seed — DONE (2.1–2.2)

- **2.1–2.2**: RED — `test_team_with_no_played_games_appears_as_an_all_zero_row` referenced `App\Services\StandingsService`, which didn't exist. GREEN — created `app/Services/StandingsService.php` with `forSeason(Season $season): Collection` doing only the roster query (`Team::where('season_id', ...)->orderBy('id')->get()->mapWithKeys(...)`) mapped to `StandingRow` instances, no games query yet (deferred to Phase 3 per tasks.md's own phase split).

### Phase 3: Game fold — `accumulate()` — DONE (3.1–3.6)

- **3.1–3.2**: RED — `test_team_with_win_draw_loss_has_correct_row` (3 constructed games via a new private `playGame(Matchday, Team home, Team away, ?int, ?int)` fixture helper) asserted `played=3, won=1, drawn=1, lost=1`, exact GF/GA sums, `goal_difference=-1`, `points=4` — failed (`played` stayed `0`, no games query existed). GREEN — added the `Game::query()` fetch and a private `accumulate(array &$rows, int $teamId, int $for, int $against)` folding the home perspective only; test passed.
- **3.3–3.4**: RED — `test_home_and_away_games_fold_into_the_same_team` (1 home game + 1 away game) asserted `played=2` with GF/GA summed across both roles — failed (`played` stayed `1`, only home fold ran). GREEN — added the second `accumulate()` call with swapped args for the away perspective, per D3's "one code path, so away cannot drift from home" design.
- **3.5–3.6**: RED — `test_game_with_both_scores_null_is_excluded` and `test_game_with_only_one_score_set_is_excluded` (both directions: `home_score` set/`away_score` null, and the mirror) — failed with a live `TypeError` (`accumulate(): Argument #3 ($for) must be of type int, null given`), a stronger RED than a plain assertion failure since it proves the code path was genuinely reached with unfiltered data. GREEN — added `whereNotNull('home_score')`/`whereNotNull('away_score')` to the games query; both tests passed.

### Phase 4: Scoping + sort — DONE (4.1–4.8)

- **4.1–4.2**: RED (real, not vacuous — see deviation note below) — `test_games_from_another_season_do_not_affect_this_seasons_table` constructs a game under a **season-B matchday that references season-A's own teams** (not the more obvious "season-B teams playing under season-B's matchday" shape, which the pre-existing D5 `isset()` guard already handles trivially and would not have gone RED). Failed (`played` came back `2` instead of `1` — the cross-season game leaked in). GREEN — added `whereHas('matchday', fn (Builder $q) => $q->where('season_id', $season->getKey()))` to the games query (D3); test passed.
- **4.3–4.4**: RED — `test_rows_are_ordered_by_points_then_goal_difference_then_goals_for` (pair A/B tied on points, differ on GD; pair C/D tied on points **and** GD, differ on GF) asserted the exact team-name order `[A, C, D, B]` — failed (unsorted, roster-id order `[A, B, C, D]`). GREEN — added `->sortBy([['points','desc'],['goal_difference','desc'],['goals_for','desc']])` (D4); test passed.
- **4.5–4.6**: RED — `test_fully_tied_teams_keep_a_stable_order` (two teams identical on points/GD/GF, seeded out of id order first, then re-checked). First run caught a **test-authoring bug** (variable names `$teamLast`/`$teamFirst` didn't match creation order — fixed to `$teamLowerId`/`$teamHigherId` matching literal creation order), not a production bug. Once fixed, the test passed immediately with **zero production change** — confirms design D4's live-verified claim that `Collection::sortByMany()`'s single `uasort` is stable since PHP 8.0 (project requires `^8.3`). Recorded per tasks.md's own "confirm" framing for this pair.
- **4.7–4.8**: RED/GREEN — `test_team_outside_the_season_roster_is_not_added_to_the_table` (a game under this season's own matchday referencing a team from an unrelated season) passed on the **first run with zero production change**, because the `isset()` guard in `accumulate()` (D5) was already present — it was added in Phase 3's GREEN step directly from design's snippet, ahead of this dedicated test. See deviation note below. Verified the test is non-vacuous: without the guard, `accumulate()` would auto-vivify a malformed row entry missing the required `team` key, and the later `new StandingRow(...$row)` would throw on the missing named argument — so the test does fail loudly if the guard is removed.

### Phase 5: Reconciliation + regression — DONE (5.1–5.4)

- **5.1–5.2**: RED/GREEN — `test_demo_seed_produces_a_consistent_table` (`$this->seed()` against the real `DatabaseSeeder`) asserted 4 invariants: row count = 10, `Σplayed = 2 × playedGameCount`, `Σpoints = 3×decisive + 2×draws` (draws counted via `home_score !== away_score`, so each draw contributes 2 points total across both teams), `Σgoals_for = Σgoals_against`. Passed on the first run with zero production change, as expected — this is a reconciliation check on already-correct logic, not new behavior.
- **5.3**: Full suite — **90/90 passed** (78 baseline Fase 2/3 tests + 12 new `StandingsServiceTest` tests), zero regressions.
- **5.4**: All work committed on `fase-4/1-standings-service` (base `fase-3/4-game`), left unmerged for user review per `stacked-to-main`.

## REFACTOR pass

- Type-hinted the `whereHas` closure parameter as `Illuminate\Database\Eloquent\Builder $query` (matching design D3's own snippet style) instead of leaving it untyped.
- `vendor/bin/pint --test` run on both new `app/Services/` files and the test file — clean, no style violations.
- No further extraction warranted: `accumulate()` is already a single, small private method; no duplication across the two call sites beyond the intentional home/away symmetry design D3 calls for.

## Test suite status (final, full-suite run — task 5.3)

| Test file | Status | Count |
|---|---|---|
| `Tests\Unit\ExampleTest` | PASS | 1/1 |
| `Tests\Feature\DatabaseSeederTest` | PASS | 5/5 |
| `Tests\Feature\DeleteStrategyTest` | PASS | 2/2 |
| `Tests\Feature\ExampleTest` | PASS | 1/1 |
| `Tests\Feature\GameGuardTest` | PASS | 5/5 |
| `Tests\Feature\GameResourceTest` | PASS | 8/8 |
| `Tests\Feature\GamesRelationManagerTest` | PASS | 3/3 |
| `Tests\Feature\MatchdayResourceTest` | PASS | 7/7 |
| `Tests\Feature\PlayerFactoryTest` | PASS | 1/1 |
| `Tests\Feature\PlayerResourceTest` | PASS | 8/8 |
| `Tests\Feature\PlayersRelationManagerTest` | PASS | 3/3 |
| `Tests\Feature\RelationshipTest` | PASS | 6/6 |
| `Tests\Feature\SchemaMigrationTest` | PASS | 10/10 |
| `Tests\Feature\SeasonResourceTest` | PASS | 6/6 |
| `Tests\Feature\StadiumRelationManagerTest` | PASS | 2/2 |
| `Tests\Feature\StandingsServiceTest` | PASS | 12/12 |
| `Tests\Feature\TeamResourceTest` | PASS | 10/10 |

**Total: 90/90 tests, 269 assertions. Zero Fase-2/3 regressions (78/78 baseline carried through unchanged).**

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1-1.2 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | N/A (new) | ✅ `Class not found` confirmed | ✅ 1/1 passed | ✅ 2nd test: all-zero defaults path | ➖ None needed |
| 2.1-2.2 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | ✅ 2/2 (prior tests in file) | ✅ `Class not found` confirmed | ✅ 1/1 passed | ➖ Single scenario (roster-only skeleton) | ➖ None needed |
| 3.1-3.2 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | ✅ 3/3 (prior tests in file) | ✅ assertion failure confirmed (`played` stayed 0) | ✅ 1/1 passed | ✅ covered by 3.3-3.4's away-side case | ➖ None needed |
| 3.3-3.4 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | ✅ 4/4 (prior tests in file) | ✅ assertion failure confirmed (`played` stayed 1) | ✅ 1/1 passed | ✅ 2 cases: home-only (3.1-3.2), home+away (this test) | ➖ None needed |
| 3.5-3.6 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | ✅ 5/5 (prior tests in file) | ✅ live `TypeError` confirmed (stronger RED than assertion failure) | ✅ 2/2 passed | ✅ 2 cases: both-null, mirror one-set-one-null | ➖ None needed |
| 4.1-4.2 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | ✅ 7/7 (prior tests in file) | ✅ assertion failure confirmed (`played=2` not `1`) — genuine RED, see deviation note | ✅ 1/1 passed | ➖ Single scenario (D3 scoping) | ➖ None needed |
| 4.3-4.4 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | ✅ 8/8 (prior tests in file) | ✅ assertion failure confirmed (unsorted roster order) | ✅ 1/1 passed | ✅ 2-level tie-break exercised in one test (pair A/B + pair C/D) | ➖ None needed |
| 4.5-4.6 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | ✅ 9/9 (prior tests in file) | ✅ first attempt caught a test-authoring bug (fixed); re-run passed with 0 production change | ✅ 1/1 passed | ➖ Single scenario (stability confirmation) | ➖ None needed |
| 4.7-4.8 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed) | ✅ 10/10 (prior tests in file) | N/A — passed on first run (guard pre-existed from Phase 3); verified non-vacuous by reasoning through guard removal | ✅ 1/1 passed | ➖ Single scenario (D5 defense) | ➖ None needed |
| 5.1-5.2 | `tests/Feature/StandingsServiceTest.php` | Feature (DB-backed, `$this->seed()`) | ✅ 11/11 (prior tests in file) | N/A — passed on first run, reconciliation check on already-correct logic | ✅ 1/1 passed | ➖ Single scenario (whole-seed reconciliation) | ➖ None needed |
| 5.3 | All 12 new + 78 carried files, full suite | Full-suite re-run | ✅ 78/78 baseline | N/A (pre-existing + already-green new tests) | ✅ 90/90 passed | N/A | N/A |

### Test Summary
- **Total tests written**: 12 (`StandingsServiceTest`)
- **Total tests passing (cumulative)**: 90/90
- **Layers used**: Feature (DB-backed, `RefreshDatabase`) — 12 new; carried Feature/Unit — 78
- **Approval tests**: None — no refactoring-of-existing-code tasks in this phase (all new files)
- **Pure functions created**: 0 top-level pure functions, but `StandingRow`'s constructor is a pure derivation (`goal_difference`, `points` computed with no side effects) and `accumulate()` is a small, deterministic mutation of a local accumulator with no external side effects

## Deviations from design.md / tasks.md (flagged, not silent)

1. **Task 4.1's naive test shape doesn't RED.** tasks.md's scenario name ("games from another season do not affect this season's table") most naturally maps to "season B's own teams play season B's own game" — but with the D5 `isset()` guard already in `accumulate()`, that shape is caught by D5 alone (different team ids never collide across seasons in this schema) and never exercises D3's `whereHas()` scoping at all — a false GREEN per strict-tdd's "GREEN that passes trivially" warning. Fixed by constructing a **cross-contaminated** fixture instead: a game under season B's own matchday that references season A's own teams (violates no FK constraint — nothing in the schema forces a game's teams to share its matchday's season, exactly D5's stated assumption). This shape only whereHas() scoping can catch, and it genuinely RED'd before the fix.
2. **The D5 `isset()` guard was added a phase early.** Design's `accumulate()` snippet (D3) includes the `isset()` guard inline, and I copied the whole snippet verbatim during Phase 3's GREEN step (task 3.2) rather than only the minimum needed for that task's test — meaning task 4.7-4.8's dedicated "D5 defense" test never got a true RED (it passed immediately with 0 production change, "confirm" pattern, similar to 4.5-4.6). This is a minor Three-Laws deviation (wrote slightly more code than the current test strictly required); flagged rather than silently accepted. No functional impact — the guard is correct and matches design exactly; the ordering of *which test proves it first* is the only thing that shifted. Verified non-vacuous by reasoning through what removing the guard would do (a malformed accumulator entry missing the required `team` key, throwing on `new StandingRow(...$row)`).
3. **Test-authoring bug caught by RED, not a design gap** (task 4.5-4.6): the first draft of `test_fully_tied_teams_keep_a_stable_order` had variable names (`$teamLast`/`$teamFirst`) that didn't match their actual creation order, producing a failing assertion that looked like a stable-sort failure but was actually a test bug. Fixed by renaming to `$teamLowerId`/`$teamHigherId` matching literal creation order; the corrected test then passed with zero production change, confirming design D4's claim as originally stated.

No other deviations. `StandingRow` and `StandingsService` match design D1-D5 exactly: two-query approach, single `accumulate()` called twice with swapped args, `sortBy` with the explicit nested array form, `Collection` return type, snake_case properties, no `position`/`rank` property, tests in `tests/Feature/` not `tests/Unit/`.

## Exact final state

- All 22/22 tasks marked `[x]` in `tasks.md`, across all 5 phases.
- `design.md`: all 5 Open Questions were already `[x]` at design time — this apply run confirmed them live, most notably D4's stability claim (verified via a real fully-tied fixture) and D5's isset guard (verified non-vacuous by reasoning through its removal).
- Working tree (uncommitted at write time, committed at task 5.4): `app/Services/StandingRow.php` (new), `app/Services/StandingsService.php` (new), `tests/Feature/StandingsServiceTest.php` (new, 12 tests), `openspec/changes/fase-4-standings-service/{tasks.md,apply-progress.md,state.yaml}` (modified).
- No migrations, no model changes, no route, no Filament file, no config edit — matches proposal's stated scope exactly. `app/Services/` is the codebase's first Services-layer directory, as intended.
