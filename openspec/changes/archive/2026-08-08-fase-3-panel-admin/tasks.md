# Tasks: Fase 3 — Panel admin: Filament Resources

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1500-2000 (33 new PHP files + tests) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 -> PR 2 -> PR 3 -> PR 4, stacked |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Branch (base -> tip) | Est. lines |
|------|------|-----------------------|------------|
| 1 | SeasonResource + MatchdayResource, D3 uniques, D5 nav | `fase-2/5-storage-and-verify` -> `fase-3/1-season-matchday` | ~350 |
| 2 | TeamResource + crest upload + StadiumRelationManager + D4 delete | `fase-3/1-season-matchday` -> `fase-3/2-team-stadium` | ~350 |
| 3 | PlayerResource + PlayersRelationManager + position filter | `fase-3/2-team-stadium` -> `fase-3/3-player` | ~300 |
| 4 | GameResource + GamesRelationManager + D2 guard + shared-form extraction | `fase-3/3-player` -> `fase-3/4-game` | ~400 |

Test runner: `docker compose exec app php artisan test`. Each RED/GREEN pair maps to design.md's Testing Strategy table.

## Phase 1: Season + Matchday (`fase-3/1-season-matchday`) — spec: "Five models have standalone CRUD Resources"

- [x] 1.1 RED `tests/Feature/SeasonResourceTest.php`: List/Create/Edit pages render 200; create+edit round-trip via `fillForm()->assertHasNoFormErrors()`
- [x] 1.2 GREEN `Seasons/SeasonResource.php` + `Schemas/SeasonForm.php` + `Tables/SeasonsTable.php` + `Pages/{List,Create,Edit}Season.php` — name, start_date, end_date
- [x] 1.3 RED: duplicate `seasons.name` -> `assertHasFormErrors(['name'])`, no QueryException (D3)
- [x] 1.4 GREEN: `->unique(ignoreRecord: true)` on `SeasonForm` name field
- [x] 1.5 RED `tests/Feature/MatchdayResourceTest.php`: List/Create/Edit render 200 + round-trip
- [x] 1.6 GREEN `Matchdays/MatchdayResource.php` + `Schemas/MatchdayForm.php` + `Tables/MatchdaysTable.php` + `Pages/*` — season_id Select, number, date
- [x] 1.7 RED: duplicate `matchdays(season_id, number)` -> form error (D3)
- [x] 1.8 GREEN: season-scoped `unique()` on `MatchdayForm` number field
- [x] 1.9 GREEN: `navigationGroup` on both resources + `->navigationGroups(['League','Competition'])` on `AdminPanelProvider.php` (D5)
- [x] 1.10 Run full suite, confirm green, no Fase 2 regressions

## Phase 2: Team + Stadium (`fase-3/2-team-stadium`) — spec: "Stadium via RelationManager", "crest upload"

- [x] 2.1 RED `tests/Feature/TeamResourceTest.php`: List/Create/Edit render 200 + round-trip
- [x] 2.2 GREEN `Teams/TeamResource.php` + `Schemas/TeamForm.php` + `Tables/TeamsTable.php` + `Pages/*` — season_id Select, name, short_name, founded_year
- [x] 2.3 RED: duplicate `teams(season_id, name)` -> form error (D3)
- [x] 2.4 GREEN: season-scoped `unique()` on `TeamForm` name field
- [x] 2.5 RED: `Storage::fake('public')` + `UploadedFile::fake()->image()` -> `crest_path` starts with `crests/`, file exists on fake disk
- [x] 2.6 GREEN: `FileUpload crest_path` (`->disk('public')->directory('crests')`) on `TeamForm` + `ImageColumn` on `TeamsTable`
- [x] 2.7 RED: `StadiumRelationManager` create persists and links stadium to team (HasOne)
- [x] 2.8 GREEN `Teams/RelationManagers/StadiumRelationManager.php` (hide `CreateAction` once `stadium()->exists()`) + wire `TeamResource::getRelations()`
- [x] 2.9 RED: deleting a Team with games -> danger notification, team still exists, no unhandled exception (D4)
- [x] 2.10 GREEN: wrap `TeamResource` `DeleteAction` (table row + Edit header) in `try/catch(QueryException)` -> `Notification::danger()->send(); $action->halt();`
- [x] 2.11 Run full suite, confirm green

## Phase 3: Player (`fase-3/3-player`) — spec: "Player via PlayerResource and RelationManager"

- [x] 3.1 RED `tests/Feature/PlayerResourceTest.php`: List/Create/Edit render 200 + round-trip
- [x] 3.2 GREEN `Players/PlayerResource.php` + `Schemas/PlayerForm.php` + `Tables/PlayersTable.php` + `Pages/*` — team_id Select, name, position fixed `Select`, birth_date, shirt_number
- [x] 3.3 RED: duplicate `players(team_id, shirt_number)` -> form error (D3)
- [x] 3.4 GREEN: team-scoped `unique()` on `PlayerForm` shirt_number field
- [x] 3.5 RED: `PlayersTable` `SelectFilter position` returns only matching records
- [x] 3.6 GREEN: badge `position` column + `SelectFilter` on `PlayersTable`
- [x] 3.7 RED: `PlayersRelationManager` on team A lists only A's players; create adds directly to team
- [x] 3.8 GREEN `Teams/RelationManagers/PlayersRelationManager.php` reusing `PlayerForm` minus `team_id` + wire `TeamResource::getRelations()`
- [x] 3.9 Run full suite, confirm green

## Phase 4: Game (`fase-3/4-game`) — spec: "Game via GameResource and RelationManager", D2 guard

- [x] 4.1 RED `tests/Feature/GameResourceTest.php`: List/Create/Edit render 200 + round-trip
- [x] 4.2 GREEN `Games/GameResource.php` + `Schemas/GameForm.php` + `Tables/GamesTable.php` + `Pages/*` — matchday_id Select (custom option label), home_team_id Select `->live()`, away_team_id Select, kickoff_at, home_score/away_score nullable numeric (no default)
- [x] 4.3 RED: equal home/away team -> `assertHasFormErrors(['away_team_id'])`, `Game::count()` unchanged; repeat with int-vs-string mismatched types (pins V7)
- [x] 4.4 GREEN: closure `rule()` on `away_team_id` — `(int) $value === (int) $get('home_team_id')` fails with message
- [x] 4.5 RED: empty score inputs persist as `null`, not `0` (pins V8)
- [x] 4.6 GREEN: confirm `home_score`/`away_score` have no `->default(0)`/`dehydrateStateUsing` (should already pass per V8; adjust only if RED fails)
- [x] 4.7 RED: `GamesRelationManager` on a matchday lists/edits only that matchday's games; equal-team guard still fires inside the RM modal
- [x] 4.8 GREEN: extract `GameForm::teamAndScoreFields()` static method; `Matchdays/RelationManagers/GamesRelationManager.php` reuses it minus `matchday_id`; wire `MatchdayResource::getRelations()`
- [x] 4.9 Run full suite, confirm green, confirm zero Fase 2 regressions (Testing Strategy #10)
- [x] 4.10 Verify `canAccessPanel()` was not added anywhere in this change (admin-panel delta: role restriction stays deferred beyond Fase 3) — `rg canAccessPanel app/`, expect no new matches
