# Proposal: Fase 4 — StandingsService

> Roadmap: **Fase 4 (Lógica de liga)** per `ARQUITECTURA.md`. Builds on archived Fase 2 (domain) and Fase 3 (admin CRUD).

## Intent

Fase 3 made results enterable; nothing turns them into a league table. "Who is winning?" — the first question any football site answers — has no code path, and Fase 5's public views are blocked on it. `ARQUITECTURA.md` §3 fixes the shape: "la clasificación se calcula a partir de los resultados; no se guarda como fuente de verdad, se deriva". Fase 4 delivers that derivation as the codebase's **first** `App\Services\` class.

## Scope

### In Scope

- `app/Services/StandingsService.php` — one class, one public entry point, e.g. `forSeason(Season $season)`.
- Per-team row: `played`, `won`, `drawn`, `lost`, `goals_for`, `goals_against`, `goal_difference`, `points` (3/1/0).
- Ordering: points DESC → **goal difference** DESC → **goals for** DESC (user decision, locked).
- Season scoping defensively via `matchday.season_id` (Game has no `season_id`).
- Unit tests with hand-picked scores (`GameFactory::played()` is randomized — unusable for exact assertions).

### Out of Scope

- Any persisted `standings` table, cache layer, or migration.
- **Filament widget / dashboard preview — explicitly deferred.**
- Blade views, routes, controllers → **Fase 5**.
- Head-to-head or any tie-break beyond GD → GF (user decision, locked).
- Per-player stats, divisions, form guide, live recalculation hooks.

## Capabilities

### New Capabilities

- `league-standings`: derived table computation, played-game definition, points/tie-break rules, ordering, empty and partial-season behavior.

### Modified Capabilities

None — no schema, model, or admin requirement changes.

## Approach

Approach 3 from exploration: one scoped read of played games (`whereNotNull` on **both** scores, via `matchday.season_id`), fold into per-team accumulators with a `Collection`, derive GD/points, sort. Rows return as a DTO/value object — not an Eloquent model, since `Standing` is non-persisted by design.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Services/` | New | Directory + `StandingsService` (first Services-layer code) |
| `tests/Unit/` | New | Constructed-fixture standings tests |
| `app/Models/`, `database/` | Unchanged | Read-only consumers |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| A half-entered result (one score `null`) silently counts | Med | Require **both** scores non-null; spec it as a scenario |
| Cross-season team leaks into a table | Low | Scope by `matchday.season_id`, never by `Team.season_id` alone |
| N+1 or full-table scan on ~380 games | Low | Single scoped query; no per-team queries |
| Service shape set by guesswork (no precedent) | Med | Design phase decides return shape + DI explicitly |

## Rollback Plan

Purely additive, read-only. Delete `app/Services/` and the new test files; nothing else references them. No migration, no data, no config change.

## Dependencies

- Fase 2 schema (`Season`, `Team`, `Matchday`, `Game`) and demo seeder.
- Docker `app` container for `php artisan test`.

## Success Criteria

- [ ] Given a season's games, the table is correctly computed and correctly ordered.
- [ ] Unplayed games are excluded from every counter, including `played`.
- [ ] GD-then-GF tie-break applied, covered by a constructed test.
- [ ] A full-season run on Fase 2 demo data yields a plausible table (Σ`played` = 2 × played games; points reconcile).
- [ ] No `standings` table, cache, route, or Filament widget added.
- [ ] `php artisan test` passes; no Fase 2/3 regressions.

## Open Questions for Design

1. Return shape: plain array vs. a `StandingRow` DTO / value object.
2. Class shape: stateless direct invocation vs. constructor-injected DI. No `App\Services\` precedent exists — Fase 4 sets the convention.
3. Team universe: include zero-game teams as all-zero rows, or only teams with played games?
