# Archive Report: Fase 4 — StandingsService

**Date**: 2026-08-08
**Change**: fase-4-standings-service
**Project**: Liga Amazon
**Artifact Store**: openspec
**Archive Location**: `openspec/changes/archive/2026-08-08-fase-4-standings-service/`

## Executive Summary

Fase 4 (StandingsService) has been successfully archived. The change is complete, verified clean (PASS, 0 CRITICAL, 0 WARNING), and all 22 tasks are done. The delta spec for league standings has been synced into the main project specs at `openspec/specs/league-standings/spec.md`. The change folder has been moved to the archive with date prefix. The SDD cycle is closed and the change is ready for branch merge review.

## Artifacts Archived

All artifacts from `openspec/changes/fase-4-standings-service/` have been moved to `openspec/changes/archive/2026-08-08-fase-4-standings-service/`:

| Artifact | Status | Notes |
|----------|--------|-------|
| proposal.md | Archived | Intent, scope, approach, risks, rollback plan, success criteria |
| exploration.md | Archived | Current state analysis, approaches considered, testing notes, open questions |
| design.md | Archived | Technical approach, 5 architecture decisions (D1-D5), data flow, file changes, interfaces, testing strategy, future-proofing, open questions (all resolved) |
| tasks.md | Archived | 22 tasks across 5 phases; all marked [x] complete. Review workload forecast: Low (370 lines, no chaining). |
| apply-progress.md | Archived | Full apply phase record with 22/22 tasks completed. 90/90 tests pass (78 baseline + 12 new). TDD cycle evidence table. Three flagged deviations (all documented and verified). No regressions. |
| verify-report.md | Archived | Verification PASS verdict. 90/90 tests, 269 assertions, all 7 spec scenarios COMPLIANT. Zero CRITICAL, zero WARNING, one non-blocking SUGGESTION (coverage tooling). All 3 flagged deviations spot-checked and confirmed. |
| state.yaml | Archived | Phase states: exploration ✓, proposal ✓, spec ✓, design ✓, tasks ✓, apply ✓, verify ✓, archive ✓. Decisions locked. Out-of-scope items. Archive summary. |
| specs/league-standings/spec.md | Archived | Delta spec for the new "league-standings" capability: 4 requirements, 7 scenarios, 16 detailed assertions. |

## Main Specs Synced

| Domain | Action | Location | Details |
|--------|--------|----------|---------|
| league-standings | Created | `openspec/specs/league-standings/spec.md` | New, non-persisted standings computation service. 4 requirements (per-team stats, played-game definition, ordering, season scoping + zero-game teams). 7 scenarios with full GWT format. No existing main spec — delta spec became the primary spec. |

**No existing specs were modified** — Fase 4 adds new capability only. No schema, model, or admin changes required.

## Task Completion Summary

**Total tasks**: 22
**Complete**: 22 [x]
**Incomplete**: 0
**Status**: All phases GREEN (1.1-1.2, 2.1-2.2, 3.1-3.6, 4.1-4.8, 5.1-5.4)

### Test Results
- Full suite: 90/90 PASS (269 assertions)
- Baseline (Fase 2/3): 78/78 PASS (zero regressions)
- New (Fase 4): 12/12 PASS (all scenarios + design-level guarantees)
- Layers: 1 file (`tests/Feature/StandingsServiceTest.php`), Feature-level, DB-backed, RefreshDatabase, PHPUnit 12

### Spec Compliance
| Requirement | Scenario | Test | Result |
|-------------|----------|------|--------|
| R1 Per-team aggregate stats | Correct stats for a team with played games | test_team_with_win_draw_loss_has_correct_row | COMPLIANT |
| R1 Per-team aggregate stats | Home and away games both fold | test_home_and_away_games_fold_into_the_same_team | COMPLIANT |
| R2 Played-game definition | Fully unplayed game is excluded | test_game_with_both_scores_null_is_excluded | COMPLIANT |
| R2 Played-game definition | Half-entered game is excluded | test_game_with_only_one_score_set_is_excluded | COMPLIANT |
| R3 Ordering | Multi-level tie-break | test_rows_are_ordered_by_points_then_goal_difference_then_goals_for | COMPLIANT |
| R4 Season scoping | Cross-season games excluded | test_games_from_another_season_do_not_affect_this_seasons_table | COMPLIANT |
| R4 Season scoping | Zero-game team appears | test_team_with_no_played_games_appears_as_an_all_zero_row | COMPLIANT |

**All 7/7 spec scenarios COMPLIANT** with real covering tests (5 design-level tests also present).

## Verification Status

**Verdict**: **PASS**
**Issues**: 0 CRITICAL, 0 WARNING, 1 SUGGESTION (coverage tooling, non-blocking)
**Completeness**: 22/22 tasks ✓, all code matches state ✓, all spec scenarios covered ✓
**Build & Tests**: 90/90 PASS on independent re-run ✓
**Scope Lock**: No persisted standings table, no Filament widget, no Blade view, no route ✓

See `verify-report.md` for full details.

## Design Decisions Locked

All 5 architecture decisions from the design phase have been implemented and verified:

- **D1**: Return type is `Collection<int, StandingRow>` of readonly value objects (not arrays). Snake_case properties, no position/rank property.
- **D2**: Stateless `final class`, instance method (not static), no constructor arguments, no service-provider binding.
- **D3**: Two independent queries (roster seed + played games), single `accumulate()` called twice with swapped args for home/away symmetry.
- **D4**: Explicit nested `sortBy` array form; stable sort via PHP 8.0+ `uasort`; full ties preserve roster `orderBy('id')`.
- **D5**: Defensive `isset()` guard in `accumulate()` drops off-roster teams silently; in-roster opponent still gets stats.

Plus 5 additional "decisions_locked" entries from state.yaml covering tie-break rules, phase scope, points formula, played signal, multi-season handling.

## Known Deviations (All Documented)

Per `apply-progress.md`, three deviations were flagged and verified:

1. **Task 4.1 season-scoping test fixture** — Cross-contaminated fixture (season-B matchday referencing season-A teams) used instead of naive fixture; genuinely RED's before fix, exercises D3's `whereHas` scoping rather than relying on D5 guard alone. Verified non-vacuous.

2. **D5 `isset()` guard added a phase early** — Guard was included in Phase 3's GREEN step (from design's snippet), so Phase 4's dedicated D5 test passed on first run. No functional impact; guard is correct and matches design exactly. Verified non-vacuous by reasoning through guard removal.

3. **Test-authoring bug in 4.5-4.6** — Variable naming bug (`$teamLast`/`$teamFirst` vs. creation order) caught by RED; once fixed, test passed with zero production change. Confirms design D4's stable-sort claim.

All three verified against source; no hidden issues found.

## Out of Scope (Documented in state.yaml)

Explicitly deferred to later phases:
- Filament widget/admin preview of standings (future widget)
- Public Blade views for fans (Fase 5)
- Persisted standings table or cache layer (by design)
- Head-to-head tie-break (rejected, only GD→GF)
- Point deductions/sanctions (no administrative adjustments)
- Multi-season/all-time tables (single-season only)

## Files Archived (Byte-Identical Copies)

The original files in `openspec/changes/fase-4-standings-service/` are now byte-identical to those in the archive at `openspec/changes/archive/2026-08-08-fase-4-standings-service/`:

```
2026-08-08-fase-4-standings-service/
├── proposal.md
├── exploration.md
├── design.md
├── tasks.md
├── apply-progress.md
├── verify-report.md
├── state.yaml
└── specs/
    └── league-standings/
        └── spec.md
```

**Original folder location** (`openspec/changes/fase-4-standings-service/`) still contains these files for reference until explicitly deleted via `git rm`. The archive copy is the primary record going forward.

## Migration & Rollback

**No migration required** — Fase 4 added:
- `app/Services/StandingsService.php` (new, non-persisted)
- `app/Services/StandingRow.php` (new, non-persisted)
- `tests/Feature/StandingsServiceTest.php` (new, 12 tests)

**No schema, migration, config, or Filament changes.** Rollback = delete the three new files. All Fase 2/3 data and behavior unchanged.

## Next Steps

1. **Git workflow (user/orchestrator responsibility)**: The branch `fase-4/1-standings-service` (based on `fase-3/4-game`) remains unmerged per `stacked-to-main` strategy. The user will review and merge it manually. The SDD artifact archive is complete and ready for that review.

2. **Fase 5 (if planned)**: Can now consume `StandingsService::forSeason()` for public Blade views. The service is container-resolvable (`StandingsService $standings` type-hint works), and the return type (`Collection<StandingRow>`) is Blade-iterable.

3. **Future enhancements**: Point deductions, divisions, caching, H2H tie-breaks, or coverage tooling can be added without touching the core standings logic (single-location derivations per design).

## Traceability

This archive report closes the SDD cycle for **fase-4-standings-service**:
- Proposal → Exploration → Spec → Design → Tasks → Apply → Verify → **Archive** ✓

All artifacts are preserved in `openspec/changes/archive/2026-08-08-fase-4-standings-service/`. The main spec is now integrated at `openspec/specs/league-standings/spec.md`. The git branch `fase-4/1-standings-service` remains unmerged on the stacked-to-main chain for user review.

**SDD cycle complete.**
