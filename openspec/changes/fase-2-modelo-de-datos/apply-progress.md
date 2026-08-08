# Apply Progress: Fase 2 — Modelo de datos

> All 28 tasks complete. Apply phase done — ready for `sdd-verify`.

## Current position

- **HEAD branch**: `fase-2/5-storage-and-verify` (created off `fase-2/4-seeder` tip `def98ff`)
- **Chain** (stacked-to-main, each branching from the previous tip):
  1. `fase-2/1-migrations` (off `master`) — commit `08993d9`
  2. `fase-2/2-models` (off unit 1 tip) — commit `c2e1791`
  3. `fase-2/3-factories` (off unit 2 tip) — commit `199b44a`
  4. `fase-2/4-seeder` (off unit 3 tip) — commit `dd5af2b`, checkpoint doc `def98ff`
  5. `fase-2/5-storage-and-verify` (off unit 4 tip) — **this batch**

None of the 5 branches are merged into `master` or into each other beyond the intended stack — all left unmerged for user review, per `chain_strategy: stacked-to-main`.

## Task status (28/28 tasks — ALL DONE)

### Phase 1: Migrations — ALL DONE (1.1–1.9)
Committed on `fase-2/1-migrations`. 12/12 tests passing (`SchemaMigrationTest`, `DeleteStrategyTest`). Migrations applied to dev DB, rollback verified clean (reverse FK order, `migrate:rollback --step=6`), then re-migrated.

**Infra fix found and applied during this phase**: `php artisan test` was silently running against the real dev MySQL database instead of SQLite `:memory:` (design D5's assumption), because `docker-compose.yml`'s `app.environment` block sets `DB_CONNECTION=mysql` etc. as real OS-level env vars, which Laravel's `Illuminate\Support\Env` prioritizes over PHPUnit's `<env>` overrides (even with `force="true"`). Fixed in `tests/TestCase.php::createApplication()` by forcing `database.default`/`database.connections.sqlite.database` directly on the config repository after boot. `phpunit.xml` also got `force="true"` added to its `<env>` block (defense-in-depth/documentation). No `.env*` files or `docker-compose.yml` were touched.

### Phase 2: Models — ALL DONE (2.1–2.3)
Committed on `fase-2/2-models`. `GameGuardTest` 5/5 passing (equal-team int, equal-team string, mixed-type int-vs-string triangulation, `withoutEvents()` bypass documented, distinct-teams success case). 6 models created with `#[Fillable]` + `casts()` convention matching `app/Models/User.php`.

### Phase 3: Factories — ALL DONE (3.1–3.4)
Committed on `fase-2/3-factories`. `PlayerFactoryTest` (1/1) + `RelationshipTest` (6/6, all bidirectional) passing. `PlayerFactory` uses a `Sequence` in `configure()` for shirt numbers. Task 3.4: `RelationshipTest` passed on first run (no new production code needed) — recorded as such rather than forcing an artificial RED.

### Phase 4: Seeder — ALL DONE (4.1–4.3)
Committed on `fase-2/4-seeder` (commit `dd5af2b`). `DatabaseSeederTest` 5/5 passing, 12 assertions: exact row counts (1/10/10/180/18/90), 50 played MD1-10 / 40 unplayed MD11-18, 0 equal-team games, 0 non-null `crest_path`.

**TDD note**: the "no equal-team game" and "no crest_path" RED assertions initially passed vacuously on zero seeded rows (empty-collection false-positive). Caught per strict-tdd assertion-quality rules; strengthened with a precondition count assertion before treating RED as genuinely proven.

`DatabaseSeeder::run()` implements FK-ordered seeding using a circle-method double round-robin (9+9 rounds × 5 pairings = 90 games / 18 matchdays), guaranteeing distinct home/away teams by construction — required because the seeder uses `WithoutModelEvents`, which mutes `Game::booted()`'s guard (design D3). Docblock note for D3 is present on the `DatabaseSeeder` class.

### Phase 5: Storage / nginx — ALL DONE (5.1–5.4) — completed this batch

- **5.1**: Created `storage/app/public/crests/.gitignore` (`*` / `!.gitignore`).
  **Found during apply (not in original design)**: the existing `storage/app/public/.gitignore` (`*` + `!.gitignore`) blocks git from ever traversing into a new subdirectory by name — a nested `.gitignore` alone is invisible to git unless the parent negates the directory first. Fixed by adding `!crests/` to `storage/app/public/.gitignore`. Verified via `git check-ignore -v storage/app/public/crests/.gitignore` returning non-ignored after the fix (previously matched by `storage/app/public/.gitignore:1:*`).
- **5.2**: Ran `php artisan storage:link` inside the `app` container → `INFO The [public/storage] link has been connected to [storage/app/public].` Verified `is_link('/var/www/html/public/storage')` → `true` inside `app`. Verified the same symlink is visible from the `web` (nginx) container via `MSYS_NO_PATHCONV=1 docker compose exec web ls -la /var/www/html/public/storage` (shared bind mount, no separate volume — `lrwxrwxrwx ... -> /var/www/html/storage/app/public`). Probe-wrote `crests/_probe.txt` via `Storage::disk('public')->put(...)` through `php artisan tinker --execute=...`. `curl -sf -w "%{http_code}" http://localhost:8080/storage/crests/_probe.txt` → **`200 OK`**, body `ok`.
- **5.3**: **Not triggered.** The symlink probe succeeded on the first attempt — the nginx alias fallback (`location /storage/ { alias ...; }`) was NOT added to `docker/nginx/default.conf`, and `web` was NOT restarted. Confirmed via `git diff --stat docker/nginx/default.conf` (empty output — file genuinely untouched).
- **5.4**: Deleted the probe file (`Storage::disk('public')->delete('crests/_probe.txt')`, confirmed `exists()` → `false`). Re-curled → file no longer served (`403`, expected — falls through nginx's catch-all `location /` → Laravel, no route). **Resolved mechanism: symlink (`storage:link`).** The NTFS bind-mount symlink-failure risk anticipated by design D4/proposal's Risks table did NOT materialize in this Docker Desktop/WSL2 environment.

### Phase 6: Full-suite verification — ALL DONE (6.1–6.3) — completed this batch

- **6.1**: `docker compose exec app php artisan test` — single invocation, all 7 test files together. **31/31 passed, 52 assertions, 32.63s.** Files: `Tests\Unit\ExampleTest` (1), `Tests\Feature\DatabaseSeederTest` (5), `Tests\Feature\DeleteStrategyTest` (2), `Tests\Feature\ExampleTest` (1), `Tests\Feature\GameGuardTest` (5), `Tests\Feature\PlayerFactoryTest` (1), `Tests\Feature\RelationshipTest` (6), `Tests\Feature\SchemaMigrationTest` (10). No regressions vs. the per-phase cumulative sweeps.
- **6.2**: `php artisan migrate:fresh --seed --force` against the real dev MySQL DB. Manually confirmed `proposal.md` Success Criteria via `php artisan tinker`:
  - Row counts: `seasons=1, teams=10, stadiums=10, players=180, matchdays=18, games=90` (exact match).
  - `home_score IS NOT NULL` count = 50 (MD1-10 played); `home_score IS NULL` count = 40 (MD11-18 unplayed).
  - `home_team_id = away_team_id` count = 0.
  - `crest_path IS NOT NULL` count = 0.
  - Relationships resolve both directions: `Team::with('season','players','stadium','homeGames','awayGames')->first()` → season name populated, 18 players, stadium populated, 18 combined home+away games; `Season->teams` = 10, `Season->matchdays` = 18.
  - `Game::create(['home_team_id' => X, 'away_team_id' => X, ...])` against the real MySQL connection → `ValidationException`: "A team cannot play against itself." (guard confirmed outside the SQLite test env too).
- **6.3**: `php artisan migrate:rollback --step=6 --force` — all 6 domain tables dropped cleanly in reverse FK order (games → matchdays → players → stadiums → teams → seasons), **no FK errors**. Re-ran `php artisan migrate --force` afterward to leave the dev DB migrated (schema present, domain tables empty) — same "sane state" convention established at the end of Phase 1; deliberately not reseeded.

### Phase 7: Docs — ALL DONE (7.1–7.2) — completed this batch

- **7.1**: Storage mechanism (symlink) recorded directly in `tasks.md` Phase 5 (5.2/5.4 entries above).
- **7.2**: `design.md`'s Open Question on storage mechanism marked `[x]` resolved, with the same symlink-confirmed detail and an explicit divergence note that the nginx alias remains a documented-but-inactive fallback for future environments where the NTFS/bind-mount symlink failure D4 anticipated might actually occur.

## Exact final state

- All 28/28 tasks marked `[x]` in `tasks.md`.
- `design.md` Open Questions: storage mechanism item resolved; the unrelated `restrictOnDelete()` friendly-UI-error item stays open (explicitly deferred to Fase 3, non-blocking, not in this change's scope).
- Working tree: Unit 5 files staged/committed on `fase-2/5-storage-and-verify` — `storage/app/public/crests/.gitignore` (new), `storage/app/public/.gitignore` (modified — added `!crests/`), `openspec/changes/fase-2-modelo-de-datos/{tasks.md,design.md,state.yaml}` (modified). `docker/nginx/default.conf` genuinely untouched (fallback not needed).
- Dev DB: migrated, domain tables present and empty (post-rollback-then-remigrate sanity state).

## Test suite status (final, single full-suite invocation — task 6.1)

| Test file | Status | Count |
|---|---|---|
| `Tests\Unit\ExampleTest` | PASS | 1/1 |
| `Tests\Feature\DatabaseSeederTest` | PASS | 5/5 |
| `Tests\Feature\DeleteStrategyTest` | PASS | 2/2 |
| `Tests\Feature\ExampleTest` | PASS | 1/1 |
| `Tests\Feature\GameGuardTest` | PASS | 5/5 |
| `Tests\Feature\PlayerFactoryTest` | PASS | 1/1 |
| `Tests\Feature\RelationshipTest` | PASS | 6/6 |
| `Tests\Feature\SchemaMigrationTest` | PASS | 10/10 |

**Total: 31/31 tests, 52 assertions, single `php artisan test` invocation covering all 7 files together.**

## TDD Cycle Evidence (Phase 5-7 — this batch)

Phase 5-7 tasks are infrastructure verification/documentation, not new production logic driven by unit tests — they are explicitly carved out of the strict-tdd RED/GREEN cycle by design.md's Testing Strategy ("Storage serving (D4) is verified manually via the sequence above — not automatable in SQLite/PHPUnit") and by `tasks.md`'s own phrasing (5.x = manual verification steps, 6.x = full-suite/manual DB verification, 7.x = docs). No new test files were required or written in this batch; all 31 tests exercised in 6.1 were written in prior phases (1-4) under strict TDD, evidenced in the prior `apply-progress.md` checkpoint (now merged above) and in `tasks.md`'s Phase 1-4 entries.

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 5.1-5.4 | N/A — manual verification (design.md explicitly excludes storage serving from automated tests) | Manual/Integration (curl + tinker) | N/A | N/A | ✅ probe write + curl 200 confirmed | N/A (single scenario: symlink attempt) | N/A |
| 6.1 | All 7 existing test files (no new tests) | Full-suite re-run | ✅ 31/31 (baseline = target, no regressions) | N/A (pre-existing tests) | ✅ 31/31 passed | N/A | N/A |
| 6.2-6.3 | N/A — manual DB verification (migrate:fresh/rollback are infra commands, not unit-testable business logic) | Manual/Integration | N/A | N/A | ✅ counts/guard/rollback confirmed | N/A | N/A |
| 7.1-7.2 | N/A — documentation only | N/A | N/A | N/A | N/A | N/A | N/A |

### Test Summary (this batch)
- **Total tests written this batch**: 0 (Phase 5-7 is verification/docs, not new production code — per design.md's explicit carve-out)
- **Total tests passing (cumulative, all phases)**: 31/31
- **Layers used**: Unit (Phases 1-4, prior batches), Integration (Phases 1-4, prior batches), Manual verification (Phase 5-6, this batch — HTTP curl + tinker DB assertions, per design.md's explicit non-automatable declaration for storage serving)
- **Approval tests**: None — no refactoring tasks in this batch
- **Pure functions created this batch**: 0
