# Exploration: fase-6-pulido

Roadmap phase: **Fase 6 — Pulido** (`ARQUITECTURA.md` line 321): "Diseño (integrar el
frontend de Amazon Superleague), tests y ajustes." Final roadmap phase.

## Current state

10 Blade files exist and are functional but minimally styled (plain Tailwind utilities):

| File | Role |
|---|---|
| `resources/views/components/layouts/site.blade.php` | layout shell (header/nav/footer) |
| `resources/views/site/standings.blade.php` | `/` standings |
| `resources/views/site/fixtures.blade.php` | `/partidos` |
| `resources/views/site/scorers.blade.php` | `/goleadores` |
| `resources/views/site/news/index.blade.php` | `/noticias` |
| `resources/views/site/news/show.blade.php` | `/noticias/{slug}` |
| `resources/views/components/site/standings-table.blade.php` | reusable |
| `resources/views/components/site/game-card.blade.php` | reusable |
| `resources/views/components/site/scorer-list.blade.php` | reusable |
| `resources/views/components/site/news-card.blade.php` | reusable |

**Design tokens are already wired.** `resources/css/app.css` `@theme` block already carries
every token from `openspec/design-reference/amazon-superleague/NOTES.md`: Barlow /
Barlow Condensed / IBM Plex Mono, `--color-brand: #FFB800`, `--color-ink: #1B1B1B`,
surface colors (`#2B2B2B` / `#262626` / `#383838`), and win/draw/loss colors. **Fase 6 is a
markup-and-classes job, not a tokens job.**

## Structural mismatch to resolve explicitly

The raw mockup (`Amazon Superleague.dc.html` + `NOTES.md`) is **one dense single-page
dashboard**: hero news, standings + partido destacado + líderes sidebar, fixtures strip,
noticias + fantasy — all on one URL.

The **already-merged** `openspec/specs/public-views/spec.md` routes this as **4 separate
single-concern pages**. Fase 6 does **not** re-open that merged spec's routing. It applies
the mockup's visual *language* (card style, table row layout, spacing, color, chip legend,
gradient background) to each existing page **in place**, through the component seams Fase 5's
`design.md` deliberately created for this purpose. It does **not** recreate a single-page
dashboard.

## Per-surface gaps (concrete restyle targets)

- **Layout / nav** — needs sticky header, two-tone "AMAZON SUPERLEAGUE" logo treatment,
  active-route yellow underline, and the diagonal `repeating-linear-gradient` background
  (explicitly deferred by Fase 5's `design.md` as "visual composition, Fase 6, not a token").
  No login / language switcher (no auth or i18n in scope — correctly excluded).
- **`standings-table`** — mockup shows POS / EQUIPO (crest + name) / PJ / DG / PTS plus a
  promotion/relegation colour-dot legend row. Current table shows the fuller set
  (#, Equipo, PJ, G, E, P, GF, GC, DG, PTS), divisions as stacked sections, no crest, no legend.
  **Recommendation: keep the fuller column set** (more informative, already spec'd and tested);
  apply the mockup's row styling, colour treatment, crest placeholder and legend *on top*. Do
  not delete columns merely to match the mockup's condensed variant.
- **`game-card`** — mockup: compact card, 3px top accent border, mono-font meta line, hover
  state. Current: simple flex row with one border. Needs card restyle + crest placeholders.
- **`scorer-list`** — mockup: circular avatar placeholder, stacked name/team, large yellow rank
  numeral. Current: plain ordered list. Needs avatar circle + typographic hierarchy.
- **`news-card` + index/show** — mockup: eyebrow-tag treatment plus a featured hero card for the
  top story. Current: simple 3-column grid (title + date). Apply the eyebrow tag to all cards;
  **do not** build the featured hero variant — it needs a new `News` model flag/field, i.e.
  schema work, out of scope for a pure-polish phase.
- **Footer** — currently one centred copyright line; mockup implies a fuller dark multi-column
  footer. Minor, low risk, cosmetic expansion only.

## Explicitly excluded (no domain concept — not reopened)

- **"Partido de la semana" / live badge** — no live-match or status concept exists; Fase 2
  explicitly declined a status field on `Game`.
- **Fantasy Superleague** — confirmed out of scope in Fase 5 and again here.

## Admin panel: out of scope

Filament's admin panel compiles its own stylesheet and never consumes `resources/css/app.css`
(confirmed in Fase 5's `design.md` D10 — no `->viteTheme()` registered). `NOTES.md`'s mockup is
explicitly the public-facing site only. Fase 6 touches only `resources/views/site/**`,
`resources/views/components/{layouts/site,site}/**`, and `resources/css/app.css`.

## "Tests y ajustes" — deferred-cleanup sweep

Reviewed all four archived phases' verify-reports. Exactly **one** concrete actionable item:

`database/factories/MatchdayFactory.php` sets `'number' => fake()->numberBetween(1, 18)` with no
uniqueness constraint within a season. This collides against the season-scoped unique index and
makes `GamesRelationManagerTest::test_lists_only_the_owning_matchdays_games` (a Fase-3-era test)
fail intermittently with `UniqueConstraintViolationException`. Flagged "out of scope, not
touched" across three prior phases.

Fase 5's two undocumented review-workload-budget overruns (units 2 and 4 past the 400-line
guard) are **not** actionable code changes — those branches are merged and archived, so there is
nothing to retroactively re-split. They are a process lesson for this change's own `sdd-tasks`
forecast, not a code task.

## Testing approach

Existing HTTP tests (`StandingsPageTest`, `FixturesPageTest`, `ScorersPageTest`,
`NewsPageTest`) assert on **content** (`assertSee`, status codes, 404 fallback), never on markup
or CSS classes — confirmed by grep; none assert DOM structure. A pure visual restyle can
therefore proceed without breaking any existing test, and those tests act as regression guards.

No pixel-diff or snapshot tooling exists in this stack and none should be introduced (no
precedent, disproportionate for demo scope). **Manual visual review against the mockup is the
fidelity-verification method.** The full suite (165 tests as of Fase 5, +1 for the
`MatchdayFactory` regression test) must stay green throughout.

## Scope size

Small: 10 existing Blade files + 1 CSS file + 1 factory file. No new schema, service, or
controller logic. Realistically a single work unit, comfortably under the 400-line review
budget (contrast Fase 5's 9–10 units).

## User-locked decision

Fase 6 stays a **pure visual restyle**. Do **not** add "forma reciente" (recent-form W/D/L chips,
which would need a new `recentForm()` service method) nor "partido destacado de la semana" (a
highlighted/next-game widget, which would need new query and controller logic). Both were
identified as functional additions requiring their own TDD cycle, not polish; the user declined
them for this phase. They remain documented future-extension options, not built now.
