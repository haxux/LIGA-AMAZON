# Apply Progress: Fase 3 — Panel admin: Filament Resources

> In progress. Work unit 1/4 complete (10/40 tasks). Continuing to unit 2.

## Current position

- **HEAD branch**: `fase-3/1-season-matchday` (created off `fase-2/5-storage-and-verify` tip `7bd759f`)
- **Chain** (stacked-to-main, each branching from the previous tip):
  1. `fase-3/1-season-matchday` (off `fase-2/5-storage-and-verify`) — **done, this batch**
  2. `fase-3/2-team-stadium` (off unit 1 tip) — pending
  3. `fase-3/3-player` (off unit 2 tip) — pending
  4. `fase-3/4-game` (off unit 3 tip) — pending

None of the branches will be merged into `master`/each other — all left unmerged for user review, per `chain_strategy: stacked-to-main`, matching the Fase 2 pattern.

## Task status (10/40 tasks)

### Phase 1: Season + Matchday — ALL DONE (1.1–1.10), branch `fase-3/1-season-matchday`

- **1.1-1.2**: `SeasonResourceTest.php` RED (5 tests: list/create/edit render 200, create round-trip, edit round-trip) against nonexistent `App\Filament\Resources\Seasons\Pages\*` classes → `ComponentNotFoundException` confirmed. GREEN: generated via `php artisan make:filament-resource Season` (confirms design's V2-V5 file layout live, byte-for-byte: `Seasons/SeasonResource.php` + `Seasons/Schemas/SeasonForm.php` + `Seasons/Tables/SeasonsTable.php` + `Seasons/Pages/{List,Create,Edit}Season.php`), then filled `SeasonForm` (`name` TextInput required, `start_date`/`end_date` DatePicker) and `SeasonsTable` (name/start_date/end_date + `teams_count`).
- **1.3-1.4**: RED — duplicate `seasons.name` via `CreateSeason::class` reproduced the exact failure mode D3 predicted: raw `UniqueConstraintViolationException` escaping the Livewire boundary (not a form error). GREEN: `->unique(ignoreRecord: true)` on the `name` field.
- **1.5-1.6**: Same RED→GREEN shape for `MatchdayResourceTest.php` / `MatchdayResource` (`season_id` Select relationship, `number` TextInput numeric, `date` DatePicker with helper text).
- **1.7-1.8**: RED — duplicate `(season_id, number)` reproduced the same raw-exception failure mode. GREEN — season-scoped `->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('season_id', $get('season_id')))`. Also added a cross-season non-duplicate test (triangulation: same `number` in a different season is allowed).
- **1.9**: `navigationGroup` set on both resources (`SeasonResource` → `'League'`, `MatchdayResource` → `'Competition'`) + `->navigationGroups(['League', 'Competition'])` added to `AdminPanelProvider.php` (the only edit to that file, as design D5 specifies).
- **1.10**: Full suite run — **44/44 passed** (31 baseline Fase-2 tests + 13 new), zero regressions.

**Design correction found during this phase (flagging per apply-phase rules — do not silently deviate)**: design.md's D3 snippet implies `Get $get` resolves from `Filament\Forms\Get`. Live-verified in the installed 5.7.6 tree: the closure-injectable `Get` utility actually lives at **`Filament\Schemas\Components\Utilities\Get`** (`Filament\Forms\Get` does not exist / is not what gets injected — using it throws `TypeError: Argument #2 ($get) must be of type Filament\Forms\Get, Filament\Schemas\Components\Utilities\Get given`). Confirmed by reproducing the RED failure and reading the actual injected type from the exception message. **This also applies to D2's `away_team_id` closure `rule()` in Phase 4** — will use `Filament\Schemas\Components\Utilities\Get` there too, not the `Filament\Forms\Get` implied by the design snippet.

### Phase 2: Team + Stadium — PENDING (2.1–2.11)
### Phase 3: Player — PENDING (3.1–3.9)
### Phase 4: Game — PENDING (4.1–4.10)

## Test suite status (end of Phase 1, single full-suite invocation — task 1.10)

| Test file | Status | Count |
|---|---|---|
| `Tests\Unit\ExampleTest` | PASS | 1/1 |
| `Tests\Feature\DatabaseSeederTest` | PASS | 5/5 |
| `Tests\Feature\DeleteStrategyTest` | PASS | 2/2 |
| `Tests\Feature\ExampleTest` | PASS | 1/1 |
| `Tests\Feature\GameGuardTest` | PASS | 5/5 |
| `Tests\Feature\MatchdayResourceTest` | PASS | 7/7 |
| `Tests\Feature\PlayerFactoryTest` | PASS | 1/1 |
| `Tests\Feature\RelationshipTest` | PASS | 6/6 |
| `Tests\Feature\SchemaMigrationTest` | PASS | 10/10 |
| `Tests\Feature\SeasonResourceTest` | PASS | 6/6 |

**Total: 44/44 tests, 90 assertions.**

## TDD Cycle Evidence (Phase 1)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1-1.2 | `tests/Feature/SeasonResourceTest.php` | Feature (Livewire) | N/A (new) | ✅ `ComponentNotFoundException` confirmed | ✅ 5/5 passed | ✅ create+edit round-trip, 2 scenarios | ➖ None needed (generator-shaped code) |
| 1.3-1.4 | `tests/Feature/SeasonResourceTest.php` | Feature (Livewire) | ✅ 5/5 (prior tests in file) | ✅ raw `UniqueConstraintViolationException` confirmed | ✅ `assertHasFormErrors(['name'])` passed | ➖ Single scenario (one unique field) | ➖ None needed |
| 1.5-1.6 | `tests/Feature/MatchdayResourceTest.php` | Feature (Livewire) | N/A (new) | ✅ `ComponentNotFoundException` confirmed | ✅ 5/5 passed | ✅ create+edit round-trip, 2 scenarios | ➖ None needed |
| 1.7-1.8 | `tests/Feature/MatchdayResourceTest.php` | Feature (Livewire) | ✅ 5/5 (prior tests in file) | ✅ raw `UniqueConstraintViolationException` confirmed (`TypeError` intermediate failure fixed first — see Get-namespace correction above) | ✅ `assertHasFormErrors(['number'])` passed | ✅ 2 cases: same-season duplicate rejected, cross-season duplicate allowed | ➖ None needed |
| 1.9 | N/A — navigation config, no branching logic | N/A | N/A | N/A (structural task) | N/A | Triangulation skipped: purely structural static-property assignment, single possible output | N/A |
| 1.10 | All 10 files, full suite | Full-suite re-run | ✅ 31/31 baseline | N/A (pre-existing + already-green new tests) | ✅ 44/44 passed | N/A | N/A |

### Test Summary (Phase 1)
- **Total tests written this phase**: 13 (6 `SeasonResourceTest` + 7 `MatchdayResourceTest`)
- **Total tests passing (cumulative)**: 44/44
- **Layers used**: Feature/Livewire (13 new, this phase), Unit + Feature (31, carried from Fase 2)
- **Approval tests**: None — no refactoring tasks in this phase
- **Pure functions created**: 0 (Filament Resource/Schema/Table classes are declarative configuration, not pure functions)
