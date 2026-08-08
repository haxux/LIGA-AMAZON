# Apply Progress: Fase 2 — Modelo de datos

> Mid-run interruption checkpoint. This is a resume-point document, not a final report.

## Current position

- **HEAD branch**: `fase-2/4-seeder` (clean working tree, nothing staged/unstaged — all completed work is committed)
- **Chain so far** (stacked-to-main, each branching from the previous tip):
  1. `fase-2/1-migrations` (off `master`) — commit `08993d9`
  2. `fase-2/2-models` (off unit 1 tip) — commit `c2e1791`
  3. `fase-2/3-factories` (off unit 2 tip) — commit `199b44a`
  4. `fase-2/4-seeder` (off unit 3 tip) — commit `dd5af2b` ← **current tip, HEAD**
- **Not yet created**: `fase-2/5-storage-and-verify` (Phases 5-7 not started)

None of the 4 branches have been merged into `master` or into each other beyond the intended stack — all left unmerged for user review, per the chain strategy.

## Task status (28 tasks total)

### Phase 1: Migrations — ALL DONE (1.1–1.9) ✅
Committed on `fase-2/1-migrations`. 12/12 tests passing (`SchemaMigrationTest`, `DeleteStrategyTest`). Migrations applied to dev DB (`php artisan migrate`), rollback verified clean (reverse FK order, `migrate:rollback --step=6`), then re-migrated so the dev DB is left in migrated state.

**Infra fix found and applied during this phase** (not in original design, documented in tasks.md): `php artisan test` was silently running against the real dev MySQL database instead of SQLite `:memory:` (design D5's assumption), because `docker-compose.yml`'s `app.environment` block sets `DB_CONNECTION=mysql` etc. as real OS-level env vars, which Laravel's `Illuminate\Support\Env` prioritizes over PHPUnit's `<env>` overrides (even with `force="true"`). Fixed in `tests/TestCase.php::createApplication()` by forcing `database.default`/`database.connections.sqlite.database` directly on the config repository after boot. `phpunit.xml` also got `force="true"` added to its `<env>` block (defense-in-depth/documentation, not the actual fix). No `.env*` files or `docker-compose.yml` were touched.

### Phase 2: Models — ALL DONE (2.1–2.3) ✅
Committed on `fase-2/2-models`. `GameGuardTest` 5/5 passing (equal-team int, equal-team string, mixed-type int-vs-string triangulation, `withoutEvents()` bypass documented, distinct-teams success case). 6 models created with `#[Fillable]` + `casts()` convention matching `app/Models/User.php`.

### Phase 3: Factories — ALL DONE (3.1–3.4) ✅
Committed on `fase-2/3-factories`. `PlayerFactoryTest` (1/1) + `RelationshipTest` (6/6, all bidirectional) passing. `PlayerFactory` uses a `Sequence` in `configure()` for shirt numbers. Task 3.4 note: `RelationshipTest` passed on first run (no new production code needed — models/relationships already existed from Phase 2) — recorded as such in tasks.md rather than forcing an artificial RED.

### Phase 4: Seeder — ALL DONE (4.1–4.3) ✅
Committed on `fase-2/4-seeder` (commit `dd5af2b`, the current HEAD). `DatabaseSeederTest` 5/5 passing, 12 assertions:
- Row counts: 1 season / 10 teams / 10 stadiums / 180 players / 18 matchdays / 90 games — confirmed exact via `assertSame`.
- 50 played games (MD 1–10 × 5) with non-null scores, 40 unplayed games (MD 11–18 × 5) with null scores.
- 0 games with `home_team_id === away_team_id`.
- 0 teams with non-null `crest_path`.

**TDD note**: the "no equal-team game" and "no crest_path" RED assertions initially passed *vacuously* on zero seeded rows (empty-collection false-positive). Caught per the strict-tdd assertion-quality rules; strengthened by adding a precondition count assertion (`Game::count() === 90`, `Team::count() === 10`) in the same test methods before treating RED as genuinely proven, then re-ran to confirm real RED, then GREEN.

`DatabaseSeeder::run()` implements FK-ordered seeding (Season → Team → Stadium → Player → Matchday → Game) using a circle-method double round-robin (9 first-leg + 9 return-leg rounds × 5 pairings = 90 games across 18 matchdays), which guarantees distinct home/away teams **by construction** — required because the seeder uses `WithoutModelEvents`, which mutes `Game::booted()`'s guard (design D3). Docblock note for D3 is present on the `DatabaseSeeder` class itself (task 4.3).

### Phase 5: Storage / nginx — NOT STARTED ❌ (5.1–5.4, all `[ ]`)
Nothing done yet: no `storage/app/public/crests/.gitignore`, no `storage:link` attempted, no probe-write/curl verification run, no nginx alias fallback decision made. **Storage mechanism (symlink vs nginx-alias) is UNRESOLVED** — do not report it either way; it must actually be tested per design.md's "Storage verification" mermaid sequence before recording an answer.

### Phase 6: Full-suite verification — NOT STARTED ❌ (6.1–6.3, all `[ ]`)
`php artisan test` has NOT been run as a full-suite pass since Phase 4 completed (only targeted test files were run during each phase's TDD cycle, per strict-tdd's "run only the relevant test file" rule — full-suite runs are reserved for this phase / sdd-verify). Last known state: every test file run individually up through Phase 4 was green (see counts above), and no regressions were introduced between phases (each phase's commit included a cumulative safety-net run through Phase 3; Phase 4's own 5 tests were confirmed green but a full `php artisan test` across all files was not re-run after the Phase 4 commit due to this interruption). `migrate:fresh --seed` end-to-end has NOT been run (only `db:seed` via `$this->seed()` inside PHPUnit's SQLite `:memory:` tests — the dev MySQL DB currently has empty domain tables from Phase 1's `migrate` + `rollback` + `migrate` cycle, no seed data). `migrate:rollback` full-chain (6 tables) has NOT been re-verified since Phase 1 (it was verified once in Phase 1, before models/factories/seeder existed).

### Phase 7: Docs — NOT STARTED ❌ (7.1–7.2, all `[ ]`)
Depends entirely on Phase 5's storage mechanism resolution.

## Exact next step to resume

1. `git checkout fase-2/4-seeder` (should already be there) → create `git checkout -b fase-2/5-storage-and-verify`.
2. Phase 5 (5.1–5.4): create `storage/app/public/crests/.gitignore`, run `php artisan storage:link` inside the `app` container, probe-write + curl per design.md's exact sequence, add the nginx alias fallback to `docker/nginx/default.conf` + `docker compose restart web` only if the symlink probe fails, delete the probe file, record which mechanism actually served the file.
3. Phase 6 (6.1–6.3): run full `php artisan test` (all files, not just one), run `php artisan migrate:fresh --seed` end-to-end against the real dev DB and manually confirm proposal.md's Success Criteria, then `php artisan migrate:rollback` for the full 6-table chain and confirm no FK errors.
4. Phase 7 (7.1–7.2): write the resolved storage mechanism back into `tasks.md` and mark design.md's Open Question resolved.
5. Mark tasks 5.x/6.x/7.x `[x]` in `openspec/changes/fase-2-modelo-de-datos/tasks.md` as each is genuinely done (already-completed 1.x–4.x are marked).
6. Commit Unit 5 on `fase-2/5-storage-and-verify`, leave all 5 branches unmerged for user review (per chain strategy — do not merge into `master`).

## Test suite status snapshot (last known, not a full-suite run)

| Test file | Status | Count |
|---|---|---|
| `SchemaMigrationTest` | PASS (last run: end of Phase 3 cumulative sweep) | 10/10 |
| `DeleteStrategyTest` | PASS (same sweep) | 2/2 |
| `GameGuardTest` | PASS (same sweep) | 5/5 |
| `PlayerFactoryTest` | PASS (same sweep) | 1/1 |
| `RelationshipTest` | PASS (same sweep) | 6/6 |
| `ExampleTest` (pre-existing, Fase 1) | PASS (same sweep) | 1/1 |
| `DatabaseSeederTest` | PASS (Phase 4, run in isolation, not yet re-confirmed in a full-suite sweep) | 5/5 |

Total known-green as of this checkpoint: **31 tests** across 7 files, run individually/in cumulative sweeps but **not yet in a single full-suite `php artisan test` invocation covering all 7 files together** — that combined run is Phase 6 work (task 6.1), still pending.
