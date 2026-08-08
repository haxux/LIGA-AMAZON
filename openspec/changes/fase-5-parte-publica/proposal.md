# Proposal: Fase 5 — Parte pública (+ 4 domain extensions)

> Roadmap: **Fase 5 (Parte pública)** per `ARQUITECTURA.md`. Builds on archived Fase 2/3/4.
> Scope deliberately expanded beyond the roadmap line after reviewing
> `openspec/design-reference/amazon-superleague/NOTES.md`. Delivered as ONE change,
> internally stacked work-unit branches (user's explicit choice over splitting).

## Intent

Fases 1–4 built an admin panel and a standings engine that **no visitor can see**: `routes/web.php` still
serves stock `welcome.blade.php`. Meanwhile the approved design reference exposes three domain gaps
(News, per-player goals/assists, divisions) that would force a schema re-open one phase later. Closing
them now, while the public views are being built, avoids rework and a second migration wave.

## Scope

### In Scope

**Public site** — shared Blade layout + nav; `/` standings (division-aware), `/partidos` (fixtures **and**
results in one matchday-grouped view, branching on null scores), goleadores leaderboard, noticias
listing + slug detail. NOTES.md design tokens wired into Tailwind v4 `@theme`. First HTTP-assertion
Feature tests in this codebase. Fix `ARQUITECTURA.md` §2 "Tailwind 3.x" drift.

**Domain extensions** (all locked, see `exploration.md`):

| # | Schema | Service | Admin | Public |
|---|---|---|---|---|
| 1 | `seasons.is_current` (bool, default false) | `Season::booted()` saving guard (single-current) | `Toggle` + `IconColumn` | active-season resolver w/ `latest('id')` fallback, 404 only if zero seasons |
| 2 | `news` (title, slug, body, published_at, cover_path, nullable `team_id` `nullOnDelete`) | — | `NewsResource` | listing (published only, desc) + detail |
| 3 | `game_events` (game_id/player_id `cascade`, `type` string, `minute`) | `GoalscorersService` + `ScorerRow` | `GameEventsRelationManager` | season-wide leaderboard |
| 4 | `divisions` (name, season_id, unique) + `teams.division_id` (nullable, `restrictOnDelete`) | `StandingsService::forDivision()` — new sibling, `forSeason()` untouched | `DivisionResource` + team field | table per non-empty division |

Seed: "Primera" (all 10 existing teams) + empty "Segunda".

### Out of Scope

Fantasy Superleague · pixel-perfect visual fidelity (Fase 6) · card/substitution event types ·
i18n · news comments/interactions · division-scoping the goleadores leaderboard.

## Capabilities

### New Capabilities

- `public-league-site`: public routes, 4 pages, shared layout/nav, active-season resolution + fallback.
- `player-scoring-stats`: derived goal/assist leaderboard rules (sibling of `league-standings`).

### Modified Capabilities

- `league-data-model`: **supersedes** the "`seasons` MUST NOT have an `is_current` flag" sentence; adds
  `divisions`, `teams.division_id`, `news`, `game_events`, and the division seed.
- `league-standings`: adds `forDivision()`; `forSeason()` requirements unchanged.
- `admin-league-crud`: adds Season toggle, `DivisionResource`, `NewsResource`, `GameEventsRelationManager`.

## Approach

Additive-only migrations, one per concern. Each extension mirrors an existing precedent
(`Game::booted()` guard, `crest_path` FileUpload, `StandingsService`/`StandingRow` pair, `Get`-based
Select filtering). Thin controllers resolve services from the container; Blade iterates the readonly VOs.
Public composition chain: `is_current` season → divisions with ≥1 team → `forDivision()` per table.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations/`, `database/seeders/`, `database/factories/` | New | 5 additive migrations + division seed |
| `app/Models/` | New/Modified | `Division`, `News`, `GameEvent`; `Season`/`Team` gain fields + relations |
| `app/Services/` | New/Modified | `GoalscorersService`, `ScorerRow`; `StandingsService::forDivision()` |
| `app/Filament/Resources/` | New/Modified | Divisions, News, GameEvents RM, Season form/table |
| `app/Http/Controllers/`, `routes/web.php`, `resources/views/`, `resources/css/app.css` | New/Modified | Public site + design tokens |
| `ARQUITECTURA.md`, `openspec/config.yaml` | Modified | Tailwind version drift |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Change size blows the 400-line review budget | High | Stacked work-unit branches (Fase 2/3 precedent); `sdd-tasks` slices per extension |
| `is_current` guard muted in seeder (`WithoutModelEvents`, Fase 2/D3) | Med | Only one season seeded; design states it explicitly |
| Regression in shipped `forSeason()` | Med | New sibling method only; Fase 4 tests must stay green |
| Public 500 when no season flagged current | Med | `latest('id')` fallback; 404 only if zero seasons |
| Scope creep toward Fase 6 polish | Med | Tokens yes, pixel-matching no — stated non-goal |

## Rollback Plan

Per work-unit branch: revert the branch. Migrations are additive and independently reversible
(`down()` drops the column/table). Reverting the public site is deleting routes/views/controllers —
nothing else references them. The `is_current` spec supersession is reverted by dropping the delta
before archive.

## Dependencies

Fase 2 schema + seeder, Fase 3 Filament resources, Fase 4 `StandingsService`, Docker `app` container.

## Success Criteria

- [ ] All four public pages render from seeded demo data; standings show one table per non-empty division.
- [ ] Marking a season current unsets every other; public pages follow the flag.
- [ ] Goals/assists entered per game roll up into correct season-wide leaderboards.
- [ ] `forSeason()` behavior and all Fase 2/3/4 tests unchanged and green.
- [ ] `php artisan test` and `npm run build` pass; Strict TDD followed throughout.

## Open Questions for Design

1. Exact Filament field sets for `NewsResource`, `DivisionResource`, `GameEventsRelationManager`.
2. Exact route names/URLs for the 4 public pages (`/`, `/partidos`, goleadores, noticias + detail).
3. How the shared layout/nav ties the 4 pages together; whether goleadores is its own page or a section.
