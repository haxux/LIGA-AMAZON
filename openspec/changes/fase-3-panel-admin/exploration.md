# Exploration: Fase 3 — Panel admin

> Roadmap line (`ARQUITECTURA.md`): "Panel admin — Recursos de Filament para equipos, jugadores, jornadas y partidos."
> Source: completed `sdd-explore` pass. Persisted here because it had no prior artifact.

## Current State

### Filament is installed, but has zero Resources

- Filament **v5.7.6** is installed and wired from archived Fase 1 (`openspec/changes/archive/2026-08-07-fase-1-andamiaje-docker-laravel-filament/`).
- `app/Providers/Filament/AdminPanelProvider.php` exists; panel is registered at `/admin` and HTTP-verified.
- `discoverResources()` points at `app/Filament/Resources/`, **which does not exist yet** — there are currently zero Resources. The panel renders a login and an empty dashboard.

### The domain layer is complete (Fase 2)

All 6 models exist under `app/Models/` — `Season`, `Team`, `Player`, `Stadium`, `Matchday`, `Game` — each with:

- the codebase's attribute convention (`#[Fillable]`, `#[Hidden]`, `casts()`), matching `app/Models/User.php`;
- bidirectional relationships (verified by Fase 2 feature tests);
- `Game::booted()` throwing `ValidationException::withMessages(['away_team_id' => ...])` when `home_team_id === away_team_id`.

Merged main specs: `openspec/specs/league-data-model/spec.md`, `openspec/specs/public-file-storage/spec.md`.

Notable field-level findings:

| Field | Finding | Fase 3 implication |
|---|---|---|
| `Player.position` | Plain string column, 4 curated values used by the factory: Goalkeeper, Defender, Midfielder, Forward | Natural fit for a Filament `Select` with fixed options (no enum cast needed) |
| `Team.crest_path` | `public` disk + `storage:link` fully provisioned and HTTP-verified in Fase 2 | Fase 3 only wires a `FileUpload` component — **no storage plumbing work** |
| `Game.home_score` / `away_score` | Nullable; null = not played | Score `TextInput`s must accept empty, not coerce to 0 |
| `Game` team FKs | `restrictOnDelete()` | Deleting a Team with games raises a DB-level restriction; needs a friendly surface |

### Auth / access control

- No `spatie/laravel-permission`, no `filament-shield` installed.
- No `canAccessPanel()` override exists anywhere in the codebase.
- Any authenticated `users` row reaches `/admin` — this was Fase 1's **explicit demo-scope decision**, recorded in `openspec/specs/admin-panel/spec.md` ("Panel Access Policy (Demo Scope)").

## Branch State

- Fase 2's 5 stacked branches (`fase-2/1-migrations` … `fase-2/5-storage-and-verify`) are **not yet merged into `master`**.
- Fase 2's archive step is committed: commit `7bd759f` on `fase-2/5-storage-and-verify` — specs merged into `openspec/specs/`, change folder moved to `openspec/changes/archive/2026-08-08-fase-2-modelo-de-datos/`.
- **Fase 3 must branch off `fase-2/5-storage-and-verify`**, not `master`, until Fase 2 actually merges. That branch is the only one carrying all 6 models + migrations + seeder + storage.

## Filament v5.7.6 API Notes

- v5 has **no functional API differences from v4**; the major version exists for Livewire v4 compatibility. v4 documentation and idioms apply.
- `make:filament-resource` **may** generate separate `Schemas/{Model}Form.php` + `Tables/{Model}Table.php` files (current schema-unification convention) rather than inline `form()` / `table()` methods on the Resource class.
- This must be **verified live against the actually-installed 5.7.6 stub output** during design/apply — not assumed. Design decisions about file layout depend on it.

## Open Technical Questions for Design

1. Does `ValidationException` thrown from `Game::booted()` map cleanly onto the Filament form field (`away_team_id`), or does it escape as a 500 / generic notification? Must be verified live, not assumed.
2. Which stub format does 5.7.6 actually emit (inline vs `Schemas/` + `Tables/`)? Determines the file inventory and the tasks breakdown.
3. Games: standalone `GameResource`, a `GameRelationManager` under `MatchdayResource`, or both? A matchday has exactly 5 games (score entry is naturally per-matchday), but cross-matchday browsing/filtering is also a realistic admin need.
