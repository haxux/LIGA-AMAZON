# Archive Report: Fase 5 — Parte pública (+ 4 domain extensions)

**Change**: `fase-5-parte-publica`  
**Archived to**: `openspec/changes/archive/2026-08-08-fase-5-parte-publica/`  
**Archive date**: 2026-08-08  
**Status**: COMPLETE (60/60 tasks done; verification PASS WITH WARNINGS)

## SDD Cycle Closure

All five SDD phases completed successfully:

| Phase | Artifact | Status | Date |
|-------|----------|--------|------|
| Exploration | `openspec/changes/fase-5-parte-publica/exploration.md` | DONE | 2026-08-08 |
| Proposal | `openspec/changes/fase-5-parte-publica/proposal.md` | DONE | 2026-08-08 |
| Specification | `openspec/changes/fase-5-parte-publica/specs/{public-views,league-data-model,league-standings,admin-league-crud}/spec.md` | DONE | 2026-08-08 |
| Design | `openspec/changes/fase-5-parte-publica/design.md` | DONE | 2026-08-08 |
| Tasks & Apply | `openspec/changes/fase-5-parte-publica/tasks.md` + `apply-progress.md` | DONE | 2026-08-08 |
| Verification | `openspec/changes/fase-5-parte-publica/verify-report.md` | PASS WITH WARNINGS | 2026-08-08 |
| Archive | This report | DONE | 2026-08-08 |

## Specifications Merged

Four delta specs (one new, three modifications) have been merged into the main OpenSpec source of truth:

### 1. NEW: `openspec/specs/public-views/spec.md` (Created)

Full new specification for public-facing Blade views and composition rules. Defines four public routes and their requirements:

- **Active season resolution** — flags is_current season with fallback to latest-created, 404 on zero seasons
- **Standings page** — division-aware tables, one per division with ≥1 team
- **Partidos page** — games grouped by matchday, fixture/result branching on score nullity
- **Goleadores page** — top-10 scorers + top-10 assisters, season-wide
- **Noticias page** — published-only listing and slug-based detail view

All 16 scenarios mapped to passing tests (165/165 test suite, all 32 spec scenarios across all 4 specs).

### 2. MODIFIED: `openspec/specs/league-data-model/spec.md` (Delta merged)

**Table definition updated** — Added `seasons.is_current` (boolean, default false) and `teams.division_id` (nullable FK) to the Six domain tables requirement.

**Superseded requirement** — The Fase 2 sentence "`seasons` MUST NOT have an `is_current` flag" has been explicitly replaced with the requirement that `is_current` IS required. The replacement text traces the supersession decision to Fase 5.

**ADDED Requirements (4 new)**:

1. **Single-current season invariant** — `Season::booted()` guard (mirroring `Game::booted()`) unsets `is_current` on all other seasons when one is saved as current. Zero-current is valid.

2. **Divisions** — new `divisions` table with `name` + `season_id` (cascade) + unique(season_id, name). `teams.division_id` is nullable FK with restrictOnDelete.

3. **Game events** — new `game_events` table with `game_id` + `player_id` (both cascade), `type` (PHP constants string), `minute` (nullable int).

4. **News** — new `news` table (`title`, `slug` unique, `body` text, `published_at` nullable datetime, `cover_path` nullable, `team_id` nullable FK with nullOnDelete).

5. **Demo seed** — Creates "Primera" division with all 10 teams, and empty "Segunda" for structural enablement.

All 14 scenarios across these new requirements mapped to tests (8 unique methods, all passing).

### 3. MODIFIED: `openspec/specs/league-standings/spec.md` (Delta merged)

**ADDED Requirement: Standings for a division**

`StandingsService::forDivision(Division)` — new public sibling method that computes standings scoped to a division's teams, using identical aggregate logic and ordering as `forSeason()`, with cross-division games credited only to in-division teams. Empty division returns empty collection.

`forSeason()` signature and behavior completely unchanged; all 12 existing Fase 4 tests remain green (17/17 total after adding 5 new forDivision scenarios).

### 4. MODIFIED: `openspec/specs/admin-league-crud/spec.md` (Delta merged)

**ADDED Requirements (4 new)**:

1. **Season is_current toggle** — `SeasonForm` includes Toggle for `is_current`; `SeasonsTable` includes sortable IconColumn. Toggling transparently resolves conflicts (D2, no form errors).

2. **NewsResource** — Full CRUD at panel navigation level, with `FileUpload` for `cover_path` under `news/` directory.

3. **DivisionResource** — Full CRUD at panel navigation level, scoped by season. `TeamResource` gains division Select field.

4. **GameEventsRelationManager** — Exposed on `GameResource`, player Select scoped to the game's two participating teams (via `getOwnerRecord()`, not `Get`-based filtering — the RM schema has no `home_team_id` field).

All 8 scenarios across these new requirements mapped to tests (5 unique methods, all passing).

## Spec Reconciliation Notes

### Spec Reconciliation #2 (GameEventsRelationManager Scoping)

The admin-league-crud requirement states the player Select should scope via "`GameForm`'s `Get`-based filtering pattern." This mechanism is not implementable in a RelationManager because the RM schema has no `home_team_id`/`away_team_id` fields for `Get` to read.

**Resolution**: The correct mechanism is `$this->getOwnerRecord()` — the game IS the owner record in the RM context. This achieves the same intent (scope players to the game's two squads) and passes the requirement's own scenario verbatim. Design D8 reconciles this, and both the design and the merged spec now carry the precise wording to prevent future confusion.

### Spec Reconciliation #3 (Zero-Division Fallback) — APPROVED

Design D6 adds a fallback for seasons with teams but zero divisions: render a single unnamed table from `forSeason()` instead of blank page. This state is reachable because `teams.division_id` is permanently nullable (by design). The fallback does not weaken the "one table per non-empty division" requirement — it only affects the `isEmpty()` branch.

**Status**: Approved in design; baked into unit 8; passing tests confirm the behavior (StandingsPageTest::test_page_falls_back_to_a_single_season_table_when_no_divisions_exist).

## Verification Summary

From `verify-report.md`:

- **165/165 tests passing** — full suite on real Docker environment, re-run live
- **StandingsServiceTest** isolated re-run: 17/17 passing (12 original Fase 4 + 5 new forDivision)
- **npm run build** — real build producing hashed font assets from bunny() mechanism
- **Live smoke test** — 5 public routes + admin panel against `migrate:fresh --seed`
- **All 32 spec scenarios across 4 specs** — compliant with passing test evidence
- **All 11 architecture decisions (D1-D11)** — verified line-for-line against code
- **Verdict**: PASS WITH WARNINGS
  - 0 CRITICAL issues
  - 3 WARNING (2 undocumented review-workload-budget overruns on units 2 & 4; 1 pre-existing flaky test)
  - 1 SUGGESTION (immaterial self-reporting discrepancy)

## Files Archived

The following files have been copied to `openspec/changes/archive/2026-08-08-fase-5-parte-publica/`:

- `proposal.md`
- `exploration.md`
- `design.md`
- `tasks.md` (60/60 completed)
- `apply-progress.md` (10-branch chain, unit 8 split per contingency)
- `verify-report.md` (PASS WITH WARNINGS)
- `state.yaml` (archive phase marked done)
- `specs/public-views/spec.md` (new)
- `specs/league-data-model/spec.md` (delta merged)
- `specs/league-standings/spec.md` (delta merged)
- `specs/admin-league-crud/spec.md` (delta merged)

All files are byte-identical copies from the working change folder.

## Source of Truth Updated

Main OpenSpec spec files now reflect the merged deltas:

| Spec File | Action | Details |
|-----------|--------|---------|
| `openspec/specs/public-views/spec.md` | CREATED | New capability, 7 requirements, 16 scenarios |
| `openspec/specs/league-data-model/spec.md` | MODIFIED | 1 superseded requirement, 4 added requirements |
| `openspec/specs/league-standings/spec.md` | MODIFIED | 1 added requirement (forDivision), existing untouched |
| `openspec/specs/admin-league-crud/spec.md` | MODIFIED | 4 added requirements |

All spec files verified for:
- Requirement name matching
- Scenario preservation
- Proper supersession documentation (where applicable)
- Markdown formatting and hierarchy integrity

## Archive Status

**State.yaml archive phase**: MARKED DONE

The archived `state.yaml` in `openspec/changes/archive/2026-08-08-fase-5-parte-publica/state.yaml` carries:

```yaml
archive:
  status: done
  completed_date: 2026-08-08
  summary: "All 60 SDD tasks complete; 165/165 tests passing; specs merged; change archived and source of truth updated."
```

## Files Pending Deletion

The original change folder `openspec/changes/fase-5-parte-publica/` contains the following files that still need to be deleted from the repository (the orchestrator will handle via git commands):

**Root level:**
- `openspec/changes/fase-5-parte-publica/proposal.md`
- `openspec/changes/fase-5-parte-publica/exploration.md`
- `openspec/changes/fase-5-parte-publica/design.md`
- `openspec/changes/fase-5-parte-publica/tasks.md`
- `openspec/changes/fase-5-parte-publica/apply-progress.md`
- `openspec/changes/fase-5-parte-publica/verify-report.md`
- `openspec/changes/fase-5-parte-publica/state.yaml`

**Specs subdirectory:**
- `openspec/changes/fase-5-parte-publica/specs/public-views/spec.md`
- `openspec/changes/fase-5-parte-publica/specs/league-data-model/spec.md`
- `openspec/changes/fase-5-parte-publica/specs/league-standings/spec.md`
- `openspec/changes/fase-5-parte-publica/specs/admin-league-crud/spec.md`

**Folder structure (empty after file deletion):**
- `openspec/changes/fase-5-parte-publica/specs/public-views/`
- `openspec/changes/fase-5-parte-publica/specs/league-data-model/`
- `openspec/changes/fase-5-parte-publica/specs/league-standings/`
- `openspec/changes/fase-5-parte-publica/specs/admin-league-crud/`
- `openspec/changes/fase-5-parte-publica/specs/`
- `openspec/changes/fase-5-parte-publica/` (parent directory)

Total: 11 files + 5 spec directories + 1 specs folder + 1 change folder = 18 items to remove.

## SDD Cycle Complete

The change `fase-5-parte-publica` has been fully planned (exploration → proposal), specified (4 delta specs), designed (11 architecture decisions), implemented (60 tasks across 10 stacked branches), verified (PASS WITH WARNINGS, 165/165 tests), and archived. The OpenSpec source of truth (main specs) has been updated and locked.

**Next recommended action**: User review and merge of the 10-branch stack (`fase-5/1-season-is-current` through `fase-5/9-public-scorers-news`) into the main branch per GitHub Flow workflow. The stacked branches remain unmerged in the repository pending that review.

---

**Archive prepared by**: sdd-archive executor  
**Date prepared**: 2026-08-08  
**Archive location**: `openspec/changes/archive/2026-08-08-fase-5-parte-publica/`
