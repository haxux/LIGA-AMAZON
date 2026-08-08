# Tasks: Fase 2 — Modelo de datos

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~900-1200 (6 migrations, 6 models, 6 factories, seeder, 6 test files, nginx/config) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 Migrations → PR2 Models → PR3 Factories → PR4 Seeder → PR5 Storage+verification+docs |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending (orchestrator must ask: stacked-to-main vs feature-branch-chain) |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

**Why not `size:exception` (unlike Fase 1)**: Fase 1's bulk was a generated Laravel/Docker skeleton — low review value, exception was reasonable. This change is entirely hand-written domain code (schema, relationships, integrity guards, seeding algorithm, tests) — every line is reviewable business logic. High line count here IS high review value; splitting protects reviewer attention, it doesn't just satisfy a budget number.

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Migrations + schema/cascade/restrict tests | PR 1 | Base: tracker/main. ~250-300 lines |
| 2 | Models + Game guard tests | PR 2 | Depends on PR1. ~250 lines |
| 3 | Factories + relationship test | PR 3 | Depends on PR2. ~200 lines |
| 4 | Seeder + row-count test | PR 4 | Depends on PR3. ~150-200 lines |
| 5 | Storage/nginx + full-suite verify + docs | PR 5 | Depends on PR4. ~80-120 lines |

## Phase 1: Migrations (Season → Team → Stadium → Player → Matchday → Game)

- [x] 1.1 RED `tests/Feature/SchemaMigrationTest.php`: `Schema::hasColumns()` x6 tables + composite-unique duplicate-insert throws `QueryException` (Rows 1-2). Must fail.
- [x] 1.2 RED `tests/Feature/DeleteStrategyTest.php`: Season delete cascades to dependents (Row 3); Team delete restricted while referenced by Game (Row 4). Must fail.
- [x] 1.3 GREEN migration `seasons` (name unique, timestamps).
- [x] 1.4 GREEN migration `teams` (FK season cascade, unique(season_id,name)).
- [x] 1.5 GREEN migration `stadiums` (FK team cascade+unique, 1:1).
- [x] 1.6 GREEN migration `players` (FK team cascade, unique(team_id,shirt_number)).
- [x] 1.7 GREEN migration `matchdays` (FK season cascade, unique(season_id,number)).
- [x] 1.8 GREEN migration `games` (FK matchday cascade; home/away team restrict; nullable scores/kickoff_at).
- [x] 1.9 `php artisan migrate`; confirm 1.1-1.2 green; verify `down()` drops in reverse order.

**Infra fix (found during apply, not in original design):** `php artisan test` / raw `vendor/bin/phpunit` were silently running against the real dev MySQL database instead of SQLite `:memory:` (design D5's assumption). Root cause: `docker-compose.yml`'s `app.environment` block sets `DB_CONNECTION=mysql` etc. as real OS-level env vars; Laravel's `Illuminate\Support\Env` (phpdotenv-backed) consults `$_SERVER`/`$_ENV` before the `putenv`-based adapter that PHPUnit's `<env>` (even with `force="true"`) relies on, so `phpunit.xml` overrides never actually applied. Fixed by forcing `database.default`/`database.connections.sqlite.database` in `tests/TestCase.php::createApplication()` (test-infra only, no `.env*`/docker-compose changes). Added `force="true"` to `phpunit.xml`'s `<env>` block for documentation/defense-in-depth (does not by itself fix the issue, kept for clarity). Verified via a throwaway diagnostic test (removed after verification) before the real RED tests ran.

## Phase 2: Models

- [x] 2.1 RED `tests/Feature/GameGuardTest.php`: equal home/away team (incl. string `'3'`/`'3'`) throws `ValidationException` (Row 6); same save inside `Model::withoutEvents()` NOT blocked (Row 7). Must fail.
- [x] 2.2 GREEN create `app/Models/{Season,Team,Player,Stadium,Matchday,Game}.php` — `#[Fillable]` + `casts()` + relationships per design's wiring table.
- [x] 2.3 GREEN add `Game::booted()` `saving` guard (int-cast comparison, D2). Confirm 2.1 green.

## Phase 3: Factories

- [x] 3.1 RED `tests/Feature/PlayerFactoryTest.php`: 18x factory for one team yields 18 distinct shirt numbers (Row 8). Must fail.
- [x] 3.2 GREEN `database/factories/{Season,Team,Stadium,Matchday,Game}Factory.php` — curated names/cities, no raw Faker for domain fields.
- [x] 3.3 GREEN `PlayerFactory.php` with `Sequence` state for `shirt_number`. Confirm 3.1 green.
- [x] 3.4 RED+GREEN `tests/Feature/RelationshipTest.php`: all 6 relationships resolve both directions via factories (Row 5). (Purely a verification test of already-built models/factories — passed on first run, no new production code needed; noted as such rather than forced into an artificial RED.)

## Phase 4: Seeder

- [x] 4.1 RED `tests/Feature/DatabaseSeederTest.php`: post-seed counts 1/10/10/180/18/90; MD11+ scores null; no equal-team game; all `crest_path` null (Row 9). Must fail. (Two assertions — "no equal-team game" / "no crest_path" — initially passed vacuously on zero seeded rows; strengthened with a precondition count assertion in the same test before treating RED as valid, per assertion-quality rules.)
- [x] 4.2 GREEN `database/seeders/DatabaseSeeder.php` — FK order, circle-method double round-robin (10 teams→18 matchdays×5 games), MD1-10 scored/MD11-18 null. Confirm 4.1 green. All 5 DatabaseSeederTest assertions pass (1/10/10/180/18/90 counts, 50 played MD1-10, 40 unplayed MD11-18, 0 equal-team games, 0 non-null crest_path).
- [x] 4.3 Docblock note (D3): `WithoutModelEvents` mutes the Game guard; distinct teams guaranteed by circle-method construction, not the guard. (In `DatabaseSeeder` class docblock.)

## Phase 5: Storage / nginx

- [x] 5.1 Create `storage/app/public/crests/.gitignore` (tracked dir, writable by `www-data`). **Found during apply (not in original design)**: the existing `storage/app/public/.gitignore` (`*` + `!.gitignore`) blocks git from traversing into any new subdirectory by name, including `crests/` — a nested `.gitignore` alone is invisible to git unless the parent negates the directory first. Fixed by adding `!crests/` to `storage/app/public/.gitignore` (now `*` / `!.gitignore` / `!crests/`). Verified via `git check-ignore -v storage/app/public/crests/.gitignore` returning non-ignored after the fix.
- [x] 5.2 Run `storage:link`; probe-write via `Storage::disk('public')->put('crests/_probe.txt','ok')`; `curl http://localhost:8080/storage/crests/_probe.txt`. **Result: symlink worked first try.** `is_link('/var/www/html/public/storage')` → true inside `app`; same symlink visible from the `web` container (shared bind mount, no separate volume). `curl -sf http://localhost:8080/storage/crests/_probe.txt` → `200 OK`, body `ok`.
- [x] 5.3 (Not triggered — the probe succeeded via the symlink, so the nginx alias fallback was NOT added to `docker/nginx/default.conf` and `web` was NOT restarted. `docker/nginx/default.conf` is unmodified.)
- [x] 5.4 Deleted probe file (`Storage::disk('public')->delete('crests/_probe.txt')`, confirmed `exists()` → false, re-curled → no longer served). **Resolved mechanism: symlink (`storage:link`) — the nginx alias fallback was not needed in this environment.**

## Phase 6: Full-suite verification

- [x] 6.1 `php artisan test` — confirm Phases 1-4 tests + full suite pass. **Result: 31/31 passed (52 assertions), single invocation across all 7 test files** (`ExampleTest` Unit+Feature, `DatabaseSeederTest` 5, `DeleteStrategyTest` 2, `GameGuardTest` 5, `PlayerFactoryTest` 1, `RelationshipTest` 6, `SchemaMigrationTest` 10). No regressions.
- [x] 6.2 `php artisan migrate:fresh --seed` end-to-end against the real dev MySQL DB; confirmed `proposal.md` Success Criteria manually: row counts exactly 1/10/10/180/18/90; 50 played (non-null `home_score`) / 40 unplayed (null); 0 equal-team games; 0 non-null `crest_path`. Relationships confirmed both directions via tinker (`team->season/players/stadium/homeGames/awayGames`, `season->teams/matchdays`). `Game::create()` with equal `home_team_id`/`away_team_id` confirmed rejected with `ValidationException` ("A team cannot play against itself.") against the real MySQL connection (not just SQLite tests).
- [x] 6.3 `php artisan migrate:rollback --step=6` — all 6 domain tables dropped cleanly in reverse FK order (games → matchdays → players → stadiums → teams → seasons), no FK errors. Re-ran `php artisan migrate --force` afterward to leave the dev DB migrated (schema present, domain tables empty — same "sane state" convention established in Phase 1; not reseeded).

## Phase 7: Docs

- [x] 7.1 Record the 5.4 storage mechanism back into this file. (Done directly in 5.2/5.4 above: **symlink**.)
- [x] 7.2 Mark `design.md` Open Question (storage mechanism) resolved. (See `design.md` Open Questions — first item marked `[x]` with full resolution detail.)
