# Exploration: Fase 4 — StandingsService

> Roadmap: **Fase 4 (Lógica de liga)** per `ARQUITECTURA.md` §7 line 319 —
> "`StandingsService`: cálculo de la tabla de posiciones."

## Current State

| Fact | Evidence |
|---|---|
| `app/Services/` **does not exist** | Fase 4 is the first use of the Services layer named in `ARQUITECTURA.md` §3 (line 58) and §5 (line 125). No `App\Services\` precedent to copy. |
| No `Standing` model, migration, or table | Confirmed absent repo-wide. Fase 2 deliberately left standings derived-only. |
| `Team` is season-scoped directly | `Team belongsTo Season` via a `season_id` FK → `Team::where('season_id', …)` is trivial. |
| `Game` is **not** season-scoped | `Game belongsTo Matchday`, `Matchday belongsTo Season`. Season filtering needs `whereHas('matchday', fn ($q) => $q->where('season_id', …))` or an equivalent join. |
| "Played" has no status enum | `Game.home_score` / `away_score` are nullable integers (`app/Models/Game.php:18-25`). Both set = played. This is the **sole** played signal. |
| Read side is safe | `Game::booted()` (`app/Models/Game.php:37-46`) blocks `home_team_id === away_team_id` on write, so no self-match rows exist to defend against on read. |

### Unenforced assumption (defensive-scoping driver)

Nothing in the schema constrains a game's home/away team to belong to the same
season as its matchday. `StandingsService` MUST scope by `matchday.season_id`
rather than assume every `Team` row with the target `season_id` is the universe
of participants — and vice versa.

## Approaches Considered

| # | Approach | Verdict |
|---|---|---|
| 1 | Persist a `standings` table, recompute on result save | **Rejected** — contradicts `ARQUITECTURA.md` §3: "la clasificación se calcula a partir de los resultados; no se guarda como fuente de verdad, se deriva". |
| 2 | Aggregate in SQL (`SELECT … GROUP BY` + `UNION` for home/away) | **Rejected for now** — one query, but the home/away union is opaque, hard to unit-test, and premature for a ~380-game demo season. |
| 3 | One scoped read of played games → fold to per-team stats in a PHP `Collection` → sort | **Recommended** — testable in isolation, no persistence, no cache, obvious tie-break logic. |

### Recommended (Approach 3), sketch

1. Fetch the season's played games: scope via `matchday.season_id`, filter
   `whereNotNull('home_score')->whereNotNull('away_score')`.
2. Seed one accumulator per participating team, fold each game into both the
   home and away accumulators (played / won / drawn / lost / GF / GA).
3. Derive `goal_difference` and `points` (3/1/0), then sort by points DESC,
   goal difference DESC, goals for DESC.
4. Return ordered rows as a DTO / value object per team — **not** an Eloquent
   model, since `Standing` is explicitly non-persisted.

## Testing Note (blocking for `sdd-tasks`)

`database/factories/GameFactory.php` `played()` uses **randomized** scores
(`fake()->numberBetween(0, 5)`, lines 41-44). It is unusable for
assertion-exact standings tests. Tests MUST hand-pick scores:

```php
Game::factory()->create(['home_score' => 2, 'away_score' => 1, /* … */]);
```

Tie-break scenarios in particular need constructed, not random, fixtures.

## Open Questions for Design

1. Return shape: plain array of arrays vs. a `StandingRow` DTO / value object.
2. Class shape: stateless class invoked directly vs. constructor-injected
   dependency. No `App\Services\` precedent exists — Fase 4 sets the convention
   for Fase 5 and beyond.
3. Team universe: seed accumulators from `Team::where('season_id', …)` (so
   zero-game teams appear with an all-zero row) vs. only teams appearing in
   played games. Affects the early-season empty-table experience.
