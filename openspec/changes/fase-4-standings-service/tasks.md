# Tasks: Fase 4 — StandingsService

**Branch**: `fase-4/1-standings-service` (base: `fase-3/4-game`, tip of the stacked Fase 3 chain)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~370 (2 source files ~120 lines + `StandingsServiceTest.php` ~250 lines) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | `StandingsService` + `StandingRow` + full test suite (12 methods) | PR 1 | Base: `fase-3/4-game`. Single self-contained slice; no split needed per design's own Low-risk call. |

## Phase 1: Foundation — `StandingRow` value object

- [x] 1.1 RED: add `test_row_exposes_derived_points_and_goal_difference` to `tests/Feature/StandingsServiceTest.php` (Req: R1, D1)
- [x] 1.2 GREEN: create `app/Services/StandingRow.php` per design D1 (readonly props, `points`/`goal_difference` computed in constructor); run test file

## Phase 2: Core skeleton — roster seed

- [x] 2.1 RED: add `test_team_with_no_played_games_appears_as_an_all_zero_row` (Req 4, scenario "Zero-game team")
- [x] 2.2 GREEN: create `app/Services/StandingsService.php` with `forSeason()` roster-only query → all-zero `StandingRow`s (D3); run test file

## Phase 3: Game fold — `accumulate()`

- [x] 3.1 RED: add `test_team_with_win_draw_loss_has_correct_row` + `playGame()` fixture helper (Req 1, scenario "Correct stats")
- [x] 3.2 GREEN: add games query + private `accumulate()`, fold home perspective; run test file
- [x] 3.3 RED: add `test_home_and_away_games_fold_into_the_same_team` (Req 1, scenario "Home and away")
- [x] 3.4 GREEN: call `accumulate()` twice with swapped args for home/away symmetry (D3); run test file
- [x] 3.5 RED: add `test_game_with_both_scores_null_is_excluded` + `test_game_with_only_one_score_set_is_excluded` (Req 2, both scenarios)
- [x] 3.6 GREEN: add `whereNotNull('home_score')`/`whereNotNull('away_score')` to games query; run test file

## Phase 4: Scoping + sort

- [x] 4.1 RED: add `test_games_from_another_season_do_not_affect_this_seasons_table` (Req 4, scenario "Cross-season")
- [x] 4.2 GREEN: scope roster query by `season_id` and games query via `whereHas('matchday', season_id)`; run test file
- [x] 4.3 RED: add `test_rows_are_ordered_by_points_then_goal_difference_then_goals_for` (Req 3, scenario "Multi-level tie-break")
- [x] 4.4 GREEN: apply `sortBy([['points','desc'],['goal_difference','desc'],['goals_for','desc']])->values()` (D4); run test file
- [x] 4.5 RED: add `test_fully_tied_teams_keep_a_stable_order` (D4 stability)
- [x] 4.6 GREEN: confirm stable sort preserves roster `orderBy('id')` seed order; run test file
- [x] 4.7 RED: add `test_team_outside_the_season_roster_is_not_added_to_the_table` (D5 defense)
- [x] 4.8 GREEN: confirm `isset()` guard in `accumulate()` drops off-roster teams; run test file

## Phase 5: Reconciliation + regression

- [x] 5.1 RED: add `test_demo_seed_produces_a_consistent_table` against `DatabaseSeeder` data (reconciliation invariants)
- [x] 5.2 GREEN: confirm it passes unmodified; run test file
- [x] 5.3 Run full suite `docker compose exec app php artisan test` — zero Fase 2/3 regressions
- [x] 5.4 Commit all work on `fase-4/1-standings-service` (base `fase-3/4-game`), leave unmerged per stacked-to-main
