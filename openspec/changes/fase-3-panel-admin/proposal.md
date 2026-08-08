# Proposal: Fase 3 — Panel admin: Filament Resources

> Roadmap: **Fase 3 (Panel admin)** per `ARQUITECTURA.md`. Builds on archived Fase 1 (panel) and Fase 2 (domain).

## Intent

Fase 2 made the domain real, but `app/Filament/Resources/` does not exist — `/admin` renders an empty dashboard. The only way to change league data today is `tinker`, a re-seed, or raw SQL. A league operator cannot register a team, upload a crest, sign a player, or enter a result. This change turns the panel into a usable back-office over the 6 existing models.

## Scope

### In Scope

| Resource | Notes |
|---|---|
| `SeasonResource` | Standalone; otherwise no UI path to a second season |
| `TeamResource` | + `StadiumRelationManager`, + `PlayerRelationManager` (squad), + `FileUpload` for `crest_path` |
| `PlayerResource` | Standalone: league-wide list, search, filter by `position` |
| `MatchdayResource` | + `GameRelationManager` — score entry is per-matchday (5 games) |
| `GameResource` | Standalone, cross-matchday. Team `Select`s, nullable score `TextInput`s, `home_team_id <> away_team_id` surfaced in-form |

Plus navigation grouping and PHPUnit feature tests (`strict_tdd: true`).

### Out of Scope

- Standalone `StadiumResource` / top-level Stadium nav (RelationManager only).
- `canAccessPanel()`, roles, `spatie/laravel-permission`, `filament-shield` — **deferred beyond Fase 3**; any authenticated user still reaches `/admin`.
- `StandingsService` → Fase 4. Public Blade views → Fase 5.
- Divisions, per-match player stats, News/Article — deferred by Fase 2.
- Filament installation (Fase 1). Migrations and model edits (none).

## Capabilities

### New Capabilities

- `admin-league-crud`: Resource inventory, relation managers, form/table composition, crest upload, validation surfacing.

### Modified Capabilities

- `admin-panel`: its "Panel Access Policy (Demo Scope)" scenario says role restriction is "deferred to Fase 3"; Fase 3 defers it again, so that scenario must move the deferral beyond Fase 3.

## Approach

Generate with `make:filament-resource` against the installed 5.7.6 stubs, bind fields to the existing `#[Fillable]` sets. `Player.position` → fixed `Select`. `crest_path` → `FileUpload` on the already-provisioned `public` disk (no storage plumbing). No Services layer: this is CRUD, not business logic. Both `GameResource` and `MatchdayResource → GameRelationManager` ship because the two admin jobs differ — entering a round's results vs. finding one fixture.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Filament/Resources/` | New | 5 Resources + pages + schemas/tables |
| `.../RelationManagers/` | New | Stadium, Player, Game |
| `app/Providers/Filament/AdminPanelProvider.php` | Modified | Nav grouping only |
| `tests/Feature/` | New | CRUD, upload, Game guard |
| `app/Models/`, `database/` | Unchanged | Regression surface only |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `Game::booted()` `ValidationException` escapes as a 500, not a field error | Med | Verify live; fall back to form `different()` rule |
| 5.7.6 emits `Schemas/`+`Tables/`, not inline `form()`/`table()` | Med | Inspect real stub output before tasks |
| Branching off `master` yields no models | Med | Branch off `fase-2/5-storage-and-verify` (Fase 2 unmerged) |
| Team delete hits `restrictOnDelete()` as a raw DB error | Med | Friendly guard/notification |
| 8 artifacts exceed the 400-line PR budget | High | Stacked PRs, one slice per Resource group |

## Rollback Plan

Purely additive: no migrations, no schema, no data change.

1. Delete `app/Filament/Resources/` → panel returns to the Fase 2 empty dashboard.
2. Revert the nav edit in `AdminPanelProvider.php`.
3. Delete new `tests/Feature/` files.

Crests uploaded during manual testing are inert; clear `storage/app/public/crests/*` if a clean state is wanted.

## Dependencies

- Branch `fase-2/5-storage-and-verify` (6 models, seeder, `storage:link`).
- Fase 1: Filament v5.7.6, `/admin`, a `make:filament-user` account.
- Docker `app` container for `artisan`.

## Success Criteria

- [ ] All 6 models have full CRUD reachable from `/admin` (Stadium via `TeamResource`).
- [ ] Crest upload lands on the `public` disk and renders in the Team table/form.
- [ ] Equal home/away team shows a clean inline form error — no 500, no stack trace.
- [ ] Empty scores save as `null` (not played), not `0`.
- [ ] `PlayerResource` filters by position; `TeamResource` shows only that team's squad.
- [ ] `php artisan test` passes; no Fase 2 regressions.
- [ ] No new migration, no model file modified.

## Open Questions for Design

1. Does Filament map the model `ValidationException` to `away_team_id`, or is a form-level `different('home_team_id')` required (or both)?
2. Exact 5.7.6 stub layout — inline vs `Schemas/`+`Tables/`. Verify live.
3. Should `GameResource` default-scope by season/matchday, given games grow ~5×38 per season?
