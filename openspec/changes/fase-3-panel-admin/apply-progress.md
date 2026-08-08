# Apply Progress: Fase 3 — Panel admin: Filament Resources

> In progress. Work units 1-2/4 complete (21/40 tasks). Continuing to unit 3.

## Current position

- **HEAD branch**: `fase-3/2-team-stadium` (created off `fase-3/1-season-matchday` tip `5211e03`)
- **Chain** (stacked-to-main, each branching from the previous tip):
  1. `fase-3/1-season-matchday` (off `fase-2/5-storage-and-verify`) — done, commit `5211e03`
  2. `fase-3/2-team-stadium` (off unit 1 tip) — **done, this batch**
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

### Phase 2: Team + Stadium — ALL DONE (2.1–2.11), branch `fase-3/2-team-stadium`

- **2.1-2.2**: `TeamResourceTest.php` RED (list/create/edit render + round-trip) → `ComponentNotFoundException` confirmed. GREEN: generated via `php artisan make:filament-resource Team`, filled `TeamForm` (`season_id` Select relationship, `name`/`short_name` TextInput, `founded_year` numeric) and `TeamsTable` (name/short_name/season.name + `players_count`, `SelectFilter season`). `navigationGroup` → `'League'`.
- **2.3-2.4**: RED — duplicate `(season_id, name)` reproduced the raw `UniqueConstraintViolationException` (same D3 failure mode as Phase 1). GREEN — season-scoped `unique()` using the corrected `Filament\Schemas\Components\Utilities\Get` type (see Phase 1's design-correction note; reused directly here without re-discovering the TypeError). Triangulated: cross-season duplicate name allowed.
- **2.5-2.6**: RED — `Storage::fake('public')` + `UploadedFile::fake()->image()` submitted through `fillForm(['crest_path' => $file])`; `crest_path` stayed `null` (field didn't exist yet) → `TypeError` on the assertion confirmed the gap. GREEN — `FileUpload::make('crest_path')->image()->disk('public')->directory('crests')->visibility('public')` + `ImageColumn` on the table. Assertion switched from `assertStringStartsWith`/`Storage::disk('public')->assertExists()` once the field existed → passed.
- **2.7-2.8**: New `tests/Feature/StadiumRelationManagerTest.php`. RED — referenced `Teams\RelationManagers\StadiumRelationManager`, which didn't exist → `ComponentNotFoundException`. GREEN — generated via `php artisan make:filament-relation-manager Team stadium name` (confirms design V9: `$relationship = 'stadium'`, singular class name because the relationship method is singular `stadium()`); filled `name`/`city`/`capacity` fields; `CreateAction::make()->visible(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->stadium()->doesntExist())` mirrors the unique `stadiums.team_id` constraint from the UI side. Triangulated: a second test asserts the create action is hidden once a stadium already exists (`assertTableActionHidden('create')`).
- **2.9-2.10**: RED — deleting a Team referenced by a `Game` (via both the Edit-page header `DeleteAction` and the table row `DeleteAction`) reproduced the raw `QueryException` (restrictOnDelete FK) escaping to the test — exactly the V10 failure mode. GREEN — added `TeamResource::deleteAction()`, a shared static factory wrapping `$record->delete()` in `try/catch (QueryException)` → `Notification::make()->danger()->send(); $action->halt();`, reused by both `TeamsTable`'s row action and `EditTeam`'s header action (avoids duplicating the closure across the two sites, per design D4's stated risk of duplication if done inline). Strengthened with `assertNotified('Team cannot be deleted')` on both the header-action and row-action paths (2 separate test methods) rather than only checking the team still exists.
- **2.11**: Full suite run — **56/56 passed** (44 from Phase 1 + 12 new: 10 `TeamResourceTest` + 2 `StadiumRelationManagerTest`), zero regressions.

### Phase 3: Player — PENDING (3.1–3.9)
### Phase 4: Game — PENDING (4.1–4.10)

## Test suite status (end of Phase 2, single full-suite invocation — task 2.11)

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
| `Tests\Feature\StadiumRelationManagerTest` | PASS | 2/2 |
| `Tests\Feature\TeamResourceTest` | PASS | 10/10 |

**Total: 56/56 tests, 135 assertions.**

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

## TDD Cycle Evidence (Phase 2)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 2.1-2.2 | `tests/Feature/TeamResourceTest.php` | Feature (Livewire) | ✅ 44/44 (cumulative baseline) | ✅ `ComponentNotFoundException` confirmed | ✅ 5/5 passed | ✅ create+edit round-trip, 2 scenarios | ➖ None needed |
| 2.3-2.4 | `tests/Feature/TeamResourceTest.php` | Feature (Livewire) | ✅ 5/5 (prior tests in file) | ✅ raw `UniqueConstraintViolationException` confirmed | ✅ `assertHasFormErrors(['name'])` passed | ✅ 2 cases: same-season rejected, cross-season allowed | ➖ None needed |
| 2.5-2.6 | `tests/Feature/TeamResourceTest.php` | Feature (Livewire) | ✅ 7/7 (prior tests in file) | ✅ `TypeError` on `crest_path` null confirmed field absent | ✅ `crests/` prefix + `Storage::disk('public')->assertExists()` passed | ➖ Single scenario (one upload field) | ➖ None needed |
| 2.7-2.8 | `tests/Feature/StadiumRelationManagerTest.php` | Feature (Livewire, RelationManager table action) | N/A (new file) | ✅ `ComponentNotFoundException` confirmed | ✅ 1/1 create-and-link passed | ✅ 2nd case: `assertTableActionHidden('create')` once a stadium exists | ➖ None needed (generator-shaped code) |
| 2.9-2.10 | `tests/Feature/TeamResourceTest.php` | Feature (Livewire, Action) | ✅ 8/8 (prior tests in file) | ✅ raw `QueryException` (restrictOnDelete) confirmed on both header and row action paths | ✅ `assertNotified()` + team-still-exists passed on both paths | ✅ 2 cases: Edit-page header action, table row action | ✅ extracted shared `TeamResource::deleteAction()` to avoid duplicating the try/catch closure across the 2 call sites |
| 2.11 | All 12 new + 44 carried files, full suite | Full-suite re-run | ✅ 44/44 baseline | N/A (pre-existing + already-green new tests) | ✅ 56/56 passed | N/A | N/A |

### Test Summary (Phase 2)
- **Total tests written this phase**: 12 (10 `TeamResourceTest` + 2 `StadiumRelationManagerTest`)
- **Total tests passing (cumulative)**: 56/56
- **Layers used**: Feature/Livewire (12 new, this phase), Feature/Livewire + Unit (44, carried)
- **Approval tests**: None — no refactoring tasks in this phase
- **Pure functions created**: 0 (declarative Filament configuration); one shared static factory (`TeamResource::deleteAction()`) extracted to eliminate duplication (task 2.10's REFACTOR step)
