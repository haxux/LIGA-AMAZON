# Apply Progress: Fase 6 — Pulido (Amazon Superleague visual pass)

Strict TDD mode active. Test runner: `docker compose exec app php artisan test`.
Single work unit, single branch (`fase-6/1-pulido`), based on
`fase-5/9-public-scorers-news` (Fase 5's archived tip), left unmerged for
user review — matches the Fase 2-5 pattern.

## Current position — CHANGE COMPLETE

- **Status**: ALL 20 TASKS DONE (8 phases, one work unit)
- **Branch**: `fase-6/1-pulido`, created off `fase-5/9-public-scorers-news` (commit `5991aeb`)
- **Full suite status**: 169/169 passing (`php artisan test`, 458 assertions) — exactly 165 existing + 4 new `MatchdayFactoryTest` methods, matching design's corrected success criterion
- **Build**: `npm run build` (via the `node` docker service) green — new `bg-brand-weave`/`bg-hatch` `@utility` rules compile and appear in the built CSS
- **Manual visual review**: done against live rendered HTML (see below), not just asserted
- **Next recommended phase**: `sdd-verify`

## Phase-by-phase status

### Phase 1 — MatchdayFactory fix (TDD) — DONE (3/3 tasks)

| Task | Status | Notes |
|---|---|---|
| 1.1 RED `tests/Feature/MatchdayFactoryTest.php` (4 tests) | [x] | Ran before any production change: 3 of 4 failed as expected (`UniqueConstraintViolationException` — pigeonhole-guaranteed on `numberBetween(1,18)` — plus `test_numbers_restart_per_season` failing on random values); `test_an_explicitly_passed_number_is_respected` passed trivially (expected, not a bug — explicit overrides were never broken) |
| 1.2 GREEN `database/factories/MatchdayFactory.php` — D6 (DB-`max()` + `Sequence` offset) | [x] | **Real bug found and fixed beyond design's literal code**: the design's `configure()` snippet reads `$sequence->index` *inside* the lazily-evaluated `number` closure, but `Sequence::__invoke()` increments `index` immediately after building the returned state array and *before* that inner closure ever runs — so every creation read the *already-incremented* index, producing an off-by-one (first `create()` in a season got `number = 2`, not `1`). Fixed by capturing `$offset = $sequence->index` in the *outer* sequence closure (evaluated before the increment) and closing over that captured value in the inner `number` closure. All 4 tests genuinely red before, genuinely green after. |
| 1.3 Verify — full suite + 3x `GamesRelationManagerTest` | [x] | Full suite 169/169. `GamesRelationManagerTest` run 3 separate times, 3/3 passed each time (no flake) — confirms the original intermittent collision is gone. |

### Phase 2 — CSS Foundation — DONE (1/1 task)

- [x] 2.1 Appended `@utility bg-brand-weave` and `@utility bg-hatch` to `resources/css/app.css`, exact values from the mockup source (`Amazon Superleague.dc.html` lines 28 and 57), `@theme` untouched. Verified compiled output contains both utilities via `npm run build`.

### Phase 3 — New Leaf Components — DONE (2/2 tasks)

- [x] 3.1 `resources/views/components/site/team-crest.blade.php` — `Storage::disk('public')->url($team->crest_path)` when set, neutral `bg-surface-muted` fallback span, `alt=""`, no initials/text (matches design D4 exactly).
- [x] 3.2 `resources/views/components/site/player-avatar.blade.php` — circular `bg-surface-muted` placeholder, unused `:player` prop, comment documenting the future external-API swap point (matches design D5 exactly — no fake `avatar_url` field).

### Phase 4 — Layout Shell — DONE (5/5 tasks)

- [x] 4.1 `resources/views/components/layouts/site.blade.php` — sticky header (`sticky top-0 z-40 bg-ink`), two-tone wordmark (`AMAZON` brand-bold / `SUPERLEAGUE` white), `@php $nav` array + `request()->routeIs()` active-underline loop (D3). News matches `site.news.*` so `/noticias/{slug}` also highlights.
- [x] 4.2 `<body>` → `bg-brand-weave text-ink` (was `bg-ink text-white`); `<header>`/`<footer>` kept `bg-ink text-white`.
- [x] 4.3 Footer expanded to 3-column dark layout: `COMPETICIÓN` (Clasificación, Partidos), `CLUBES` (Goleadores), `SITIO` (Noticias) — all real named routes, no dead `#` links, no login/language block. No division names anywhere.
- [x] 4.4 Header/nav/wordmark kept digit-free — no season label added.
- [x] 4.5 Regression: `StandingsPageTest` 6/6 passed, `FixturesPageTest` 3/3 passed.

### Phase 5 — Component Restyles — DONE (5/5 tasks)

- [x] 5.1 `standings-table.blade.php` — `rounded-md bg-surface` card, mono `text-[10px] tracking-[0.1em]` header row, `<x-site.team-crest>` in the team cell, `font-display text-[19px]` team names, `hover:bg-white/[0.045]` rows, brand PTS column, static `CLASIFICACIÓN`/`DESCENSO` legend row (D7 — no row coloring, no thresholds). Kept the full existing column set (#, Equipo, PJ, G, E, P, GF, GC, DG, PTS) per the locked decision — did not reduce to the mockup's condensed set.
- [x] 5.2 `game-card.blade.php` — `bg-surface-alt` card with `border-t-[3px] border-brand`, mono `JORNADA N` meta line, stacked home/away rows each with `<x-site.team-crest>` + score. Added an optional `matchdayNumber` prop so the fixtures page can pass the already-loaded matchday number and avoid an N+1 lazy-load of `$game->matchday`.
- [x] 5.3 `scorer-list.blade.php` — `<x-site.player-avatar>` + stacked `font-display` name / mono team code, `font-display text-[22px] text-brand` goal/assist count.
- [x] 5.4 `news-card.blade.php` — mono brand eyebrow tag (`NOTICIA`), `bg-hatch` slot when `cover_path` is null, `font-display` title.
- [x] 5.5 Regression: `FixturesPageTest` 3/3, `ScorersPageTest` 5/5, `NewsPageTest` 5/5 — all passed.

### Phase 6 — Page Headings + `text-ink` Audit — DONE (6/6 tasks)

- [x] 6.1–6.5 — all 5 page `<h1>` headings (`standings.blade.php`, `fixtures.blade.php`, `scorers.blade.php`, `news/index.blade.php`, `news/show.blade.php`) converted from implicit-white to explicit `text-ink`. `fixtures.blade.php`'s per-matchday `<h2>` converted to a mono `text-ink/70` label. `news/show.blade.php`'s article body wrapped in a `bg-surface` card (`p-6 rounded-md`) so the white prose stays legible against the yellow body.
- [x] 6.6 **Verification task, genuinely run** — `rg` audit of every `<h1>`/`<h2>`/`<h3>` under `resources/views/site` confirmed all 6 headings/section-labels use `text-ink` (or `text-ink/70`), none left on default/white.
  - **Real defect found and fixed beyond the literal task list**: the `text-ink` audit was correctly scoped to headings by design, but grepping `text-white` usage surfaced a *second*, more consequential class of the same root bug — several **card-internal** text nodes in the restyled components had **no explicit text color at all**, relying on inheriting `text-white` from the old `<body class="text-white">`. Once body flipped to `text-ink`, these nodes would have inherited dark-ink text rendered on dark `bg-surface`/`bg-surface-alt` cards — i.e. invisible dark-on-dark text. Found and fixed in:
    - `standings-table.blade.php`: the team-name span and all 7 stat `<td>`s (PJ/G/E/P/GF/GC/DG) had no color class → added `text-white` / `text-white/70`.
    - `game-card.blade.php`: both home/away team-name spans had no color class → added `text-white`.
    - `scorer-list.blade.php`: the player-name span had no color class → added `text-white`.
    - `news-card.blade.php`: the `<h3>` title had no color class → added `text-white`.
  - Confirmed the fix by re-fetching the live rendered `/` page (see Manual Visual Review below) and reading the actual `class` attributes in the output — all now carry explicit `text-white`/`text-white/70` inside their dark card containers, all page-level text outside cards carries `text-ink`/`text-ink/50`/`text-ink/70`.
  - Re-ran the full suite after these fixes: still 169/169 (pure additive color classes, no text-content change, no `assertSee` impact).

### Phase 7 — Storage Disk Fix — DONE (1/1 task)

- [x] 7.1 `resources/views/site/news/index.blade.php` had no direct `Storage::url()` call (only via `<x-site.news-card>`, already fixed in 5.4). `resources/views/site/news/show.blade.php`'s cover image now uses `Storage::disk('public')->url($item->cover_path)` explicitly, matching `team-crest`'s pattern — no longer relying on the `local`-disk-defaults-to-`public/storage`-symlink coincidence.

### Phase 8 — Full-Suite Checkpoint — DONE (2/2 tasks)

- [x] 8.1 Full suite: **169/169 passed, 458 assertions** (165 existing + 4 new `MatchdayFactoryTest`).
- [x] 8.2 Manual visual review — see below.

## Manual Visual Review (Phase 8.2)

No pixel-diff tooling exists (locked decision). Verification method: seeded demo
data was already present from Fase 2's `DatabaseSeeder` in the dev database, so
`curl`'d the live rendered HTML for all 4 public routes
(`/`, `/partidos`, `/goleadores`, `/noticias`) via `docker compose port web 80`
(`localhost:8080`) and read the actual output, not just asserted correctness.

Confirmed against the mockup's visual language:
- `<body class="min-h-screen bg-brand-weave font-sans text-ink">` — the diagonal
  yellow weave background and ink text are present exactly as D1 specifies.
- Header renders `sticky top-0 z-40 bg-ink text-white` with the two-tone
  `AMAZON`/`SUPERLEAGUE` wordmark and the active nav item (`Clasificación` on
  `/`) correctly showing `border-brand text-white` while inactive items show
  `border-transparent text-white/72`.
- The standings table (`/`) renders a `rounded-md bg-surface` card with a mono
  header row, crest-placeholder spans (no team in the seeded demo data has a
  `crest_path`, so all 10 rows correctly fall back to the neutral
  `bg-surface-muted` shape — the D4 fallback path is exercised, not just the
  happy path), brand-colored PTS column, and the static `CLASIFICACIÓN`/
  `DESCENSO` legend row with no row coloring. No `Segunda`/`Primera` text
  appears anywhere on the page including the footer.
- The fixtures page (`/partidos`) renders `JORNADA 1` mono meta lines per
  card, home/away rows with crest placeholders and brand-colored scores
  (e.g. `Manaos FC 1 — Marañón AC 5`), confirming both the per-section
  `<h2>Jornada N</h2>` and the per-card `JORNADA N` meta line render
  correctly and the digit ordering (`1` before `2`) is preserved.
- The scorers (`/goleadores`) and news (`/noticias`) pages have no seeded
  goal-events or news items yet in the dev DB, so both correctly render their
  empty states (`Sin datos todavía.` in `text-white/60` inside the dark card;
  `No hay noticias publicadas todavía.` in `text-ink/70` directly on the
  yellow body) — confirming the empty-state color choices are also correct on
  both backgrounds, not just the populated happy path.
- Footer's 3-column layout (`COMPETICIÓN` / `CLUBES` / `SITIO`) renders
  identically across all 4 pages with only real, named routes.

This is a real, evidence-based review (actual `class` attributes and rendered
text read from `curl` output), not an unverified "looks right" assertion.

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1/1.2 | `tests/Feature/MatchdayFactoryTest.php` | Feature | ✅ 169-baseline via full suite pre-change (all passing except this new file) | ✅ Written first, referenced no-yet-fixed factory | ✅ Passed after fix (+ a genuine off-by-one bug found during GREEN, fixed before declaring green) | ✅ 4 cases: sequential creates, batch creates, cross-season restart, explicit-number override | ➖ None needed — factory logic is a single small pure lookup |
| 1.3 | `tests/Feature/GamesRelationManagerTest.php` (pre-existing, re-run) | Feature | ✅ 3/3 baseline | N/A (approval-style regression check, not new RED) | ✅ 3/3 passed, 3 consecutive runs | N/A | N/A |

### Test Summary
- **Total tests written**: 4 (`MatchdayFactoryTest`)
- **Total tests passing**: 169/169 (full suite)
- **Layers used**: Feature (4 new + 165 pre-existing, unmodified)
- **Approval tests** (refactoring): `GamesRelationManagerTest` run 3x as a non-modifying regression/flake check
- **Pure functions created**: 1 (`MatchdayFactory::nextNumberForSeason()`)

Note on scope: Phases 2–8 are pure markup/CSS/component restyling with no new
observable business logic (per the design and state.yaml's locked decision
that this is presentation-only work with one deliberate exception — the
MatchdayFactory bug fix, which received the full RED/GREEN/TRIANGULATE cycle
above). Per the skill's TDD module, these visual changes were implemented
directly and verified via the existing `assertSee`/`assertDontSee` regression
suite after each surface, exactly as design's Testing Strategy prescribes —
not force-fit into a RED/GREEN cycle that doesn't model CSS/markup changes.

## Deviations from Design

1. **Off-by-one bug in design's own D6 code sample** (documented above under
   Phase 1) — design's `configure()` snippet as literally written reads
   `$sequence->index` from inside the lazily-evaluated `number` closure,
   which sees the *post-increment* index because `Sequence::__invoke()`
   increments `index` immediately after building the returned array, before
   the inner closure runs. Fixed by capturing the pre-increment index in the
   outer closure. The two-halves *rationale* (DB `max()` for cross-call
   uniqueness, `Sequence` offset for same-batch uniqueness) is unchanged and
   correct — only the closure-capture mechanics needed correction. Caught by
   the genuine RED/GREEN cycle exactly as it's supposed to work.
2. **Additional `text-white` fixes beyond the literal Phase 6 task list** —
   documented above under 6.6. The design correctly flagged the *heading*
   inheritance flip as the highest-probability defect, but the same root
   cause (body's text color flip) also silently broke **card-internal** text
   nodes that had no explicit color class and were relying on inheriting
   `text-white` from the old body. This is arguably an implicit design gap
   (D1's "text inside dark cards stays white" assumes those nodes had an
   explicit `text-white` already, which several component nodes in the
   *original pre-restyle* code did not — they relied on the old body-level
   `text-white` cascading down). Fixed during the audit task itself, which is
   exactly what task 6.6 was designed to catch, just scoped slightly wider
   than "headings only."

No other deviations. All other design decisions (D2, D3, D4, D5, D7, the two
test-collision constraints, the Storage disk fix) were implemented exactly as
specified.

## Issues Found

None beyond the two documented deviations above (both found and fixed within
this apply session, not left open).

## Workload / PR Boundary

- Mode: single PR (auto-chain / stacked-to-main strategy, one work unit)
- Current work unit: Unit 1 — "MatchdayFactory fix + full visual restyle"
- Boundary: starts from `fase-5/9-public-scorers-news` (Fase 5's archived
  tip), ends with this apply session's full-suite-green + manual-review
  checkpoint. Left unmerged on `fase-6/1-pulido` for user review, matching
  the Fase 2-5 pattern.
- Estimated review budget impact: Low — forecast was Medium (380-460 changed
  lines); actual measured diff (`git diff --stat` against `fase-5/9-public-scorers-news`,
  source files only) is **256 insertions + 67 deletions = 323 changed lines**,
  under the 400-line budget. No split needed.

## Status

20/20 tasks complete. Ready for `sdd-verify`.
