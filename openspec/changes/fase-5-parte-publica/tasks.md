# Tasks: Fase 5 — Parte pública (+ 4 domain extensions)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~2,390 total (sum of 9 units below) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | 9 stacked units, PR1→PR9, linear chain |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

Base for unit 1: current tip `fase-4/1-standings-service` (verified via `.git/HEAD`; no fase-5 branch exists yet, nothing merged to master). Reserved-word guard for every controller task below: namespace `App\Http\Controllers\Site` (never `...\Public`), views under `resources/views/site/`, route names `site.*`.

### Suggested Work Units (per design.md §"Work-Unit / Branch Grouping")

| # | Branch | Base | Est. lines | Spec area |
|---|---|---|---|---|
| 1 | `fase-5/1-season-is-current` | `fase-4/1-standings-service` | ~230 | league-data-model, admin-league-crud |
| 2 | `fase-5/2-divisions-schema` | unit 1 | ~400 (watch) | league-data-model, admin-league-crud |
| 3 | `fase-5/3-standings-for-division` | unit 2 | ~140 | league-standings |
| 4 | `fase-5/4-news` | unit 3 | ~330 | league-data-model, admin-league-crud |
| 5 | `fase-5/5-game-events` | unit 4 | ~330 | league-data-model, admin-league-crud |
| 6 | `fase-5/6-goalscorers-service` | unit 5 | ~200 | public-views (service layer) |
| 7 | `fase-5/7-design-tokens` | unit 6 | ~50 | (infra, no spec) |
| 8 | `fase-5/8-public-standings-fixtures` | unit 7 | ~400 (watch, near ceiling — resplit if diff exceeds 400) | public-views |
| 9 | `fase-5/9-public-scorers-news` | unit 8 | ~330 | public-views |

Strict TDD: every unit is RED (failing test) → GREEN (implement) → verify full `php artisan test` green (Fase 2/3/4 regression pin) before moving to the next unit.

## Unit 1 — `fase-5/1-season-is-current`

- [x] 1.1 RED: `SchemaMigrationTest::test_seasons_table_has_is_current_column`. Must fail.
- [x] 1.2 GREEN: migration `2026_08_08_000001_add_is_current_to_seasons_table.php`.
- [x] 1.3 RED: `SeasonCurrentGuardTest` (create, 4 methods: unset-others, create-unsets-previous, non-current-save-untouched, rename-keeps-current). Must fail.
- [x] 1.4 GREEN: `Season.php` — `#[Fillable]` += `is_current`; `casts()` += `'is_current'=>'boolean'`; `booted()` saving guard (D2, query-builder mass update, `->when($exists, whereKeyNot)`).
- [x] 1.5 GREEN: `SeasonFactory` — `'is_current'=>false` default + `current()` state.
- [x] 1.6 GREEN: `SeasonForm` `Toggle::make('is_current')` + helperText; `SeasonsTable` `IconColumn::make('is_current')->boolean()->sortable()`.
- [x] 1.7 RED: `SeasonResourceTest` += `test_toggling_is_current_unsets_the_previous_current_season_without_a_form_error`, `test_is_current_column_renders_in_the_list`. Must fail.
- [x] 1.8 GREEN: confirm 1.7 passes (no new code beyond 1.6 expected).
- [x] 1.9 RED: `DatabaseSeederTest` += `test_fresh_seed_marks_exactly_one_season_as_current`. Must fail.
- [x] 1.10 GREEN: `DatabaseSeeder` — `Season::factory()->create(['is_current'=>true, ...])` set as a direct attribute (D9, guard muted by `WithoutModelEvents`); add doc comment stating so.
- [x] 1.11 Verify: `php artisan test` full suite green (existing Fase 2/3/4 tests unaffected).

## Unit 2 — `fase-5/2-divisions-schema`

- [x] 2.1 RED: `SchemaMigrationTest` += `test_divisions_table_has_expected_columns`, `test_teams_table_has_division_id_column`, `test_duplicate_division_name_within_same_season_is_rejected`; `DeleteStrategyTest` += `test_deleting_a_season_cascades_to_divisions`, `test_deleting_a_division_with_teams_is_restricted`. Must fail.
- [x] 2.2 GREEN: migrations `..._000002_create_divisions_table.php` (season_id cascade, unique[season_id,name]), `..._000003_add_division_id_to_teams_table.php` (nullable, restrict; `down()` uses `dropConstrainedForeignId`).
- [x] 2.3 GREEN: `Division.php` (create, `#[Fillable(['season_id','name'])]`, `season()`, `teams()`) + `DivisionFactory`; `Season.php` += `divisions(): HasMany`; `Team.php` += `#[Fillable]` `division_id`, `division(): BelongsTo`.
- [x] 2.4 RED: `DivisionResourceTest` (create — list/create/edit render, duplicate name in season → form error, delete-with-teams → danger notification). Must fail.
- [x] 2.5 GREEN: `DivisionResource` 6-file layout (`app/Filament/Resources/Divisions/`), name uniqueness via `modifyRuleUsing` (TeamForm idiom), `deleteAction()` copied from `TeamResource` (`QueryException` → notification).
- [x] 2.6 RED: `TeamResourceTest` += `test_can_assign_a_team_to_a_division`, `test_team_persists_with_a_null_division`, `test_division_select_only_offers_divisions_from_the_selected_season`. Must fail.
- [x] 2.7 GREEN: `TeamForm` — `season_id` Select `->live()`; add `division_id` Select scoped via `Get('season_id')`. `TeamsTable` += division column + `SelectFilter`.
- [x] 2.8 RED: `DatabaseSeederTest` += `test_fresh_seed_creates_primera_with_all_ten_teams_and_an_empty_segunda`, `test_fresh_seed_leaves_no_team_without_a_division`. Must fail.
- [x] 2.9 GREEN: `DatabaseSeeder` — create Primera (all 10 teams) + empty Segunda per D9.
- [x] 2.10 Verify: full suite green.

## Unit 3 — `fase-5/3-standings-for-division`

- [x] 3.1 RED: `StandingsServiceTest` += 4-5 methods for `forDivision()` (division-only scoping, cross-division opponent credited, zero-game/empty-division edge cases, tie-break order matches `forSeason()`). Must fail. Existing 12 Fase-4 methods stay untouched (regression pin).
- [x] 3.2 GREEN: add public `forDivision(Division $division): Collection`.
- [x] 3.3 REFACTOR: extract private `buildTable(Collection $teams, int $seasonId)`; `forSeason()` becomes a 4-line delegation with unchanged signature/behavior (D3). `accumulate()` untouched.
- [x] 3.4 Verify: all Fase-4 `StandingsServiceTest` methods + new ones green; full suite green.

## Unit 4 — `fase-5/4-news`

- [x] 4.1 RED: `SchemaMigrationTest` += `test_news_table_has_expected_columns`, `test_news_model_maps_to_the_news_table`, `test_duplicate_news_slug_is_rejected`; `DeleteStrategyTest` += `test_deleting_a_team_nulls_its_news_team_id`. Must fail.
- [x] 4.2 GREEN: migration `..._000004_create_news_table.php` (team_id nullOnDelete, slug unique, published_at indexed).
- [x] 4.3 GREEN: `News.php` (create, `#[Fillable]`, `casts(): ['published_at'=>'datetime']`, `team()`, `#[Scope] published()`) + `NewsFactory` (+`draft()`/`scheduled()` states).
- [x] 4.4 RED: `NewsResourceTest` (create — list/create/edit render, create-with-image, duplicate slug → form error, empty `published_at` persists draft). Must fail.
- [x] 4.5 GREEN: `NewsResource` 6-file layout; `NewsForm` (title→slug live slugify, slug unique, `Textarea` body, `FileUpload` cover_path dir `news/` [crest_path pattern], `DateTimePicker` published_at, team_id Select nullable); `NewsTable`.
- [x] 4.6 Verify: full suite green.

## Unit 5 — `fase-5/5-game-events`

- [x] 5.1 RED: `SchemaMigrationTest` += `test_game_events_table_has_expected_columns`; `DeleteStrategyTest` += `test_deleting_a_game_cascades_to_its_events`, `test_deleting_a_player_cascades_to_their_events`. Must fail.
- [x] 5.2 GREEN: migration `..._000005_create_game_events_table.php` (game_id/player_id cascade, type string, minute unsignedTinyInteger, index[type,player_id]).
- [x] 5.3 GREEN: `GameEvent.php` (create — `TYPE_GOAL`/`TYPE_ASSIST`/`TYPES` consts, `casts(): ['minute'=>'integer']`, `game()`, `player()`) + `GameEventFactory` (+`goal()`/`assist()` states); `Game.php` += `events(): HasMany`; `Player.php` += `gameEvents(): HasMany`.
- [x] 5.4 RED: `GameEventsRelationManagerTest` (create — RM renders, operator records a goal, `test_player_select_only_offers_players_from_the_two_teams_in_the_game`). Must fail.
- [x] 5.5 GREEN: `GameEventsRelationManager` — player Select scoped via `$this->getOwnerRecord()->home_team_id`/`away_team_id` (owner-record mechanism, per spec's already-reconciled wording — not `GameForm`'s `Get`), type Select, minute TextInput; register in `GameResource::getRelations()`.
- [x] 5.6 Verify: full suite green.

## Unit 6 — `fase-5/6-goalscorers-service`

- [x] 6.1 RED: `GoalscorersServiceTest` (create, 8 methods: goal-only count, assist-only count, other-season excluded, zero-event players absent, desc order, default-10 limit, `null` limit returns all, tied counts stable order). Must fail.
- [x] 6.2 GREEN: `ScorerRow` VO (readonly `player`, `count`) + `GoalscorersService` (`topScorers`/`topAssisters`, SQL `groupBy`+`COUNT(*)`, `whereHas('game.matchday', season_id)` scope, `orderBy('id')` stable seed).
- [x] 6.3 Verify: full suite green.

## Unit 7 — `fase-5/7-design-tokens`

- [x] 7.1 Modify `vite.config.js`: replace the single `bunny('Instrument Sans')` entry with `bunny('Barlow Condensed', [500,600,700,800])`, `bunny('Barlow', [400,500,600,700])`, `bunny('IBM Plex Mono', [400,500])` (D10 — Laravel 13's native `laravel-vite-plugin/fonts` mechanism; no `<link preconnect>`, no CSS `@import`).
- [x] 7.2 Modify `resources/css/app.css` `@theme`: replace `--font-sans` (Barlow), add `--font-display` (Barlow Condensed), `--font-mono` (IBM Plex Mono), and the 8 brand/ink/surface/win/draw/loss color tokens per D10.
- [x] 7.3 Fix doc drift: `ARQUITECTURA.md` §2 and `openspec/config.yaml` context block — "Tailwind 3.x" → "Tailwind 4.x".
- [x] 7.4 Verify: `npm run build` green; confirm Filament admin panel unaffected (no `->viteTheme()` registered, doesn't consume `app.css`).

## Unit 8 — `fase-5/8-public-standings-fixtures`

- [x] 8.1 RED: `SeasonResolverTest` (create — flagged-current resolves, falls back to latest when none flagged, returns null when zero seasons). Must fail.
- [x] 8.2 GREEN: `SeasonResolver::active(): ?Season`.
- [x] 8.3 Delete `tests/Feature/ExampleTest.php` now (D11 — asserts `GET /` → 200 against stock `welcome.blade.php`; would go red for an unrelated reason once `/` becomes the standings page). `tests/Unit/ExampleTest.php` untouched.
- [x] 8.4 RED: `StandingsPageTest` (create — table per populated division, empty division renders none, follows `is_current` change, 404 on zero seasons, falls back to latest season, D6 zero-division fallback renders single unnamed `forSeason()` table). Must fail.
- [x] 8.5 RED: `FixturesPageTest` (create — scored matchday → result cards, unplayed → fixture cards, grouped by matchday). Must fail.
- [x] 8.6 GREEN: abstract `SiteController` (`activeSeason()` = resolver→404 chain); `StandingsController::__invoke` (`divisions()->has('teams')->orderBy('id')->get()`, D6 empty-fallback branch to `forSeason()`); `FixturesController::__invoke` (`matchdays()->with('games.homeTeam','games.awayTeam')->orderBy('number')`).
- [x] 8.7 GREEN: `resources/views/components/layouts/site.blade.php` (`@fonts`, `@vite`, nav, slot, footer); `x-site.standings-table`, `x-site.game-card` components; `resources/views/site/standings.blade.php`, `fixtures.blade.php`.
- [x] 8.8 GREEN: `routes/web.php` — `GET /` → `site.standings`, `GET /partidos` → `site.fixtures` (namespace `App\Http\Controllers\Site`).
- [x] 8.9 Delete `resources/views/welcome.blade.php` (D11).
- [x] 8.10 Verify: full suite green (Fase 2/3/4 regression); `npm run build` green. If this unit's diff exceeds ~400 lines, split standings/fixtures into two PRs before merge. **Contingency triggered**: combined diff was 423+245=668 lines; split into `fase-5/8-public-standings` (299+245) and `fase-5/8b-public-fixtures` (this commit) per the contingency clause.

## Unit 9 — `fase-5/9-public-scorers-news`

- [x] 9.1 RED: `ScorersPageTest` (create — top scorers+assisters render, zero-event player omitted, short-list no placeholders, max 10 of each, renders empty on fresh seed with zero events). Must fail.
- [x] 9.2 RED: `NewsPageTest` (create — published newest-first, drafts/future hidden, detail by slug renders published, detail 404 for draft, detail 404 for unknown slug). Must fail.
- [x] 9.3 GREEN: `ScorersController::__invoke` (`GoalscorersService::topScorers`/`topAssisters`); `NewsController::index`/`show` (`News::published()`, `show(string $slug)` not route-model-binding — visibility rule stays in one place).
- [x] 9.4 GREEN: `x-site.scorer-list`, `x-site.news-card` components; `resources/views/site/scorers.blade.php`, `site/news/index.blade.php`, `site/news/show.blade.php`.
- [x] 9.5 GREEN: `routes/web.php` — `GET /goleadores` → `site.scorers`, `GET /noticias` → `site.news.index`, `GET /noticias/{slug}` → `site.news.show` (declared after `/noticias`).
- [x] 9.6 Verify: `php artisan test` full suite green — all 32 spec scenarios across the 4 delta specs pass; `npm run build` green; manual smoke of all 4 public pages against `migrate:fresh --seed` demo data.
