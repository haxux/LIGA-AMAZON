# Proposal: Fase 2 — Modelo de datos: migraciones, modelos y relaciones

> Roadmap: **Fase 2 (Modelo de datos)** per `ARQUITECTURA.md`. Builds on archived Fase 1.

## Intent

Fase 1 left a running stack with only stock tables (`users`, `cache`, `jobs`). The domain fixed in `ARQUITECTURA.md` §4 exists only on paper, blocking everything downstream: Fase 3 has no models to bind, Fase 4 no games to compute from, Fase 5 nothing to render. This change makes the schema real and seeds demo data so later phases start from a populated league.

## Scope

### In Scope

- 6 migrations: `seasons`, `teams`, `players`, `stadiums`, `matchdays`, `games` (columns and composite uniques per `exploration.md`).
- 6 Eloquent models with full relationships, using the codebase's attribute convention (`#[Fillable]`, `#[Hidden]`, `casts()` — see `app/Models/User.php`).
- 6 factories with curated football data (names, cities, positions); per-team unique `shirt_number` via sequence.
- `DatabaseSeeder` orchestrating FK order: Season → Team → Stadium → Player → Matchday → Game.
- Storage groundwork for `Team.crest_path`: `storage:link` attempt with a committed nginx alias fallback, plus writable `storage/app/public/crests/`.
- PHPUnit feature tests (`strict_tdd: true`).

### Out of Scope

- Filament Resources / admin CRUD screens → Fase 3.
- `StandingsService` → Fase 4. Public Blade views → Fase 5.
- Divisions within a Season, News/Article, per-match player event stats — deferred by prior decision.

## Capabilities

### New Capabilities

- `league-data-model`: schema, models, relationships, integrity rules and demo seeding for the league domain.
- `public-file-storage`: end-to-end serving of the public disk under `/storage/` (crest uploads groundwork).

### Modified Capabilities

- None. `docker/nginx/default.conf` is edited, but no existing `local-dev-environment` requirement changes.

## Approach

Settled decisions (not open questions):

| Decision | Choice |
|---|---|
| Match status | `home_score`/`away_score` nullable; null = not played. No status enum. |
| FK deletes | `cascadeOnDelete()` for single-owner relations; `restrictOnDelete()` on both `Game` team FKs. |
| Crest | Real uploaded file (`crest_path`), not a color placeholder. |
| Storage serving | Try `storage:link`; nginx `location /storage/ { alias ...; }` committed as decided fallback for the NTFS symlink risk. |
| `home_team_id <> away_team_id` | Eloquent model-level guard, not a raw SQL CHECK. |
| Test runner | PHPUnit (Pest is not installed). |

`Matchday.date` is nominal only; `Game.kickoff_at` is the authoritative time.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations/` | New | 6 domain tables |
| `app/Models/` | New | 6 models + relationships |
| `database/factories/` | New | 6 factories |
| `database/seeders/DatabaseSeeder.php` | Modified | FK-ordered seeding |
| `docker/nginx/default.conf` | Modified | `/storage/` alias fallback |
| `storage/app/public/crests/` | New | Writable by `www-data` |
| `tests/Feature/` | New | Migration/model/relationship/factory tests |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `storage:link` fails on Windows/NTFS bind mount | Med | nginx alias fallback already committed; apply records which mechanism serves |
| nginx alias diverges from vanilla Laravel deploys | Med | Document both mechanisms in design |
| Factory `shirt_number` collisions | Med | Explicit sequence/pool per team |
| `restrictOnDelete()` surfaces a raw DB error on Team delete | Med | Friendly message is a Fase 3 note; teardown order handled in seeder |
| `FILESYSTEM_DISK` unverifiable (`.env` agent-blocked) | Low | Validate serving end-to-end during apply |

## Rollback Plan

No domain data exists yet, so rollback is non-destructive:

1. `php artisan migrate:rollback` — `down()` drops the 6 tables in reverse FK order (games → matchdays → players → stadiums → teams → seasons).
2. Delete new files under `app/Models/`, `database/migrations/`, `database/factories/`, `tests/Feature/`; revert `DatabaseSeeder.php` to its empty Fase 1 state.
3. Storage: drop the `/storage/` `location` block from `docker/nginx/default.conf` + `docker compose restart web`; delete the `public/storage` symlink if created and `storage/app/public/crests/`. Both are inert — leaving them is equally harmless.

Fase 1 (Docker stack, Filament, `users`) is untouched and stays green either way.

## Dependencies

- Fase 1 archived and working (Docker stack, MySQL, Filament panel).
- Docker `app` container for `artisan` commands; `web` reload for the nginx change.

## Success Criteria

- [ ] `php artisan migrate:fresh --seed` runs clean and populates all 6 tables with realistic demo data.
- [ ] All 6 relationships resolve in both directions; cascade/restrict behavior matches the decided strategy.
- [ ] A file written to the `public` disk is retrievable over HTTP at `/storage/...` (mechanism recorded).
- [ ] Saving a `Game` with equal home/away team is rejected by the model guard.
- [ ] `php artisan test` passes; new PHPUnit feature tests cover schema, relationships and factories.
- [ ] `migrate:rollback` cleanly drops all 6 tables with no FK errors.
