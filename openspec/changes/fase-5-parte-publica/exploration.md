# Exploration: Fase 5 — Parte pública (+ domain extensions)

> Consolidates two exploration passes: (1) public Blade views, (2) domain extensions
> triggered by reviewing `openspec/design-reference/amazon-superleague/NOTES.md`.

## Pass 1 — Public views

### Current state

| Area | Finding |
|---|---|
| `routes/web.php` | Only the stock `GET /` → `view('welcome')` closure. Fully greenfield. |
| `resources/views/` | Only stock `welcome.blade.php`. No layout, no components, no partials. |
| `app/Services/StandingsService.php` | `forSeason(Season $season): Collection<StandingRow>`. Stateless, no constructor → container-resolvable, directly injectable into a controller. |
| `app/Services/StandingRow.php` | Readonly VO, snake_case public props: `team`, `played`, `won`, `drawn`, `lost`, `goals_for`, `goals_against`, `goal_difference`, `points`. Trivial to iterate in Blade. |
| Models | `Season`, `Team`, `Matchday`, `Game` relationship-clean and query-ready (`Season::matchdays()`, `Matchday::games()`, `Game::homeTeam()/awayTeam()`). |
| Demo seed | 1 season, 10 teams, 18 matchdays × 5 games = 90 games; MD1–10 scored, MD11–18 null. Enough to exercise standings, past results, and future fixtures without seeder changes for the original scope. |
| Tailwind | **v4**, CSS-first `@theme` in `resources/css/app.css`, no `tailwind.config.js`. Correct for v4 — **not** a gap. |
| Doc drift | `ARQUITECTURA.md` §2 (and `openspec/config.yaml` `context`) say "Tailwind 3.x". Stale; worth a 1-line fix while touching CSS. |
| Design tokens | NOTES.md colors (`#FFB800`, `#1B1B1B`, surfaces, W/D/L chips) and fonts (Barlow Condensed / Barlow / IBM Plex Mono) are **not** wired into `app.css`. |
| Test style | No HTTP-response assertion tests exist (`$this->get(...)->assertSee(...)`). All 16 Feature test files are Filament/Livewire or service-level. This phase introduces that pattern for the first time, standard Laravel idiom + `RefreshDatabase`. |

### Route-shape recommendation

- `GET /` → standings page, driven by `StandingsService` (now division-aware, see Pass 2).
- `GET /partidos` → schedule **and** results in ONE view grouped by `Matchday`; each `Game` renders as a
  fixture-card or a result-card based on a null-check of its own scores.
  Rationale: `Game` has no status column (Fase 2 decision) — a separate `/calendario` vs `/resultados`
  split would invent a state the schema does not have.

## Pass 2 — Domain extensions

### Existing conventions to reuse

- 6 tables; attribute-based models (`#[Fillable]` + `casts()`), not `protected $fillable`.
- Filament layout: `{Plural}/{Model}Resource.php` + `{Plural}/Schemas/{Model}Form.php` +
  `{Plural}/Tables/{Plural}Table.php` (+ `RelationManagers/`).
- Image upload precedent: `TeamForm`'s `FileUpload::make('crest_path')->disk('public')->directory('crests')`.
- Model-level invariant precedent: `Game::booted()` `saving` hook (Fase 2/D2), chosen over a DB constraint.
- Conditional-field precedent: `GameForm`'s `Get`-based home/away filtering.

### Prior-art check

Fase 2's archived `design.md` "Future-Proofing Check" **already pre-approved as additive**: divisions
(nullable column/table on `teams`), player match stats (new table referencing games + players), and News
(fully decoupled entity). Fase 4's `design.md` Future-Proofing Check **forecast `forDivision()` verbatim**
as the expected evolution of `StandingsService`.

Only `is_current` is a genuine reversal: `openspec/specs/league-data-model/spec.md` currently states
"`seasons` MUST NOT have an `is_current`/active-season flag". This change MUST write a delta that
explicitly supersedes that sentence rather than silently overwriting it.

### Gaps found by NOTES.md → decisions

NOTES.md flags 4 scope gaps. Three are closed in this change (News, goleadores/asistencias, divisions);
Fantasy Superleague is confirmed out of scope (never mentioned in `ARQUITECTURA.md`).

### Locked decisions (user)

**1. `seasons.is_current`** — additive `boolean('is_current')->default(false)`. Single-current enforced by a
`Season::booted()` `saving` hook mirroring `Game`'s (no partial-unique-index trick — avoids repeating the
SQLite-vs-MySQL test-runtime divergence Fase 2/D5 already hit). Filament: `Toggle` in `SeasonForm`,
`IconColumn->boolean()->sortable()` in `SeasonsTable`. Public read: `is_current` season → fallback
`Season::latest('id')->first()` → `abort(404)` only if zero seasons exist. No transaction wrapping
(single-trusted-operator demo scope).

**2. News** — table `news`: `title`, `slug` (unique), `body` (text), `published_at` (nullable, null = draft),
`cover_path` (nullable, `public` disk / `news/` dir), `team_id` (nullable FK → teams, `nullOnDelete()` —
news is *tagged*, not owned; deleting a team must neither cascade-delete nor block). One entity covers both
general news and team-tagged "fichajes". Standard `NewsResource` layout. Public: published-only listing
(`published_at` desc) + slug detail route.

**3. Goleadores / asistencias** — table `game_events`: `game_id` (FK, `cascadeOnDelete`), `player_id`
(FK, `cascadeOnDelete` — deliberate divergence from `Game`'s `restrictOnDelete` team FKs, whose rationale
was protecting opponent history; an event has no life without its game+player pair), `type` (string with
PHP-level constants, **not** a DB enum, so a third type needs no migration), `minute` (nullable int).
New `app/Services/GoalscorersService.php` + `ScorerRow` VO (`player`, `count`), mirroring
`StandingsService`/`StandingRow`; methods `topScorers(Season)` / `topAssisters(Season)` sharing a private
aggregate helper. Not folded into `StandingsService` (different row universe: players, not teams).
Leaderboards are **season-wide, not division-scoped** (NOTES.md only confirms division tabs for the table).
Admin: `GameEventsRelationManager` on `GameResource`, player `Select` scoped to the two teams in that game
via the existing `Get`-based pattern.

**4. Divisions** — table `divisions`: `name`, `season_id` (FK, `cascadeOnDelete`, same single-owner
rationale as `teams`/`matchdays`), `unique(season_id, name)`. `teams.division_id` added by a separate
additive migration: nullable FK, `restrictOnDelete()` (forces explicit reassignment before deleting a
populated division). Stays nullable indefinitely — demo scope, no backfill.
`StandingsService::forDivision(Division $division): Collection<StandingRow>` is a **new sibling method**:
same `StandingRow` shape, roster query narrowed by `where('division_id', ...)`, identical fold/sort logic.
`forSeason()` signature and behavior stay **completely unchanged**; Fase 4 tests must stay green.
Seed: one "Primera" row with all 10 existing teams assigned (no reshuffle) + one empty "Segunda" row so the
concept is structurally enabled and visible in the panel without seeding teams into it.

### Cross-cutting integration point

Public composition chain:
`is_current` season → its divisions **having ≥1 team** → `StandingsService::forDivision()` per division
tab/table. Empty divisions (Segunda) render no table. Goleadores/asistencias bypass this chain and stay
season-wide.

### Watch-outs for design

- `DatabaseSeeder` runs under `WithoutModelEvents` (Fase 2/D3) → the `Season::booted()` `is_current` guard
  is **muted during seeding**. Harmless today (one season seeded), but design must state it explicitly.
- Only one existing capability sentence is being superseded; everything else is purely additive.
