# Tasks: Fase 6 — Pulido (Amazon Superleague visual pass)

**Branch**: `fase-6/1-pulido` (single branch, single PR — per design's Work-Unit Recommendation)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 380–460 |
| 400-line budget risk | Medium |
| Chained PRs recommended | No |
| Suggested split | Single PR — split only on the MatchdayFactory seam if actual diff exceeds 400 |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Medium

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | MatchdayFactory fix + full visual restyle | PR 1 | Base: `fase-5/9-public-scorers-news`. Kept as one unit — surfaces share D1's colour inversion; splitting by page yields unreviewable half-restyled states. If diff exceeds 400 lines, only clean split is factory-fix commit vs restyle commits (already separable, see Phase 1). |

## Phase 1: MatchdayFactory Fix (TDD, land first — independent, lower risk)

- [x] 1.1 RED — Create `tests/Feature/MatchdayFactoryTest.php`: 4 tests — unique numbers across 20 sequential `create()` calls (one season), unique numbers in one `->count(20)->create()` batch, numbers restart per season, an explicitly passed `number` is respected. Run suite; confirm all 4 fail (pigeonhole collision on current `numberBetween(1,18)`).
- [x] 1.2 GREEN — Modify `database/factories/MatchdayFactory.php`: add private `nextNumberForSeason()` (`Matchday::where('season_id',...)->max('number')+1`) plus a `configure()` `Sequence` offset (design D6 — both halves required).
- [x] 1.3 Verify — full suite green; run `GamesRelationManagerTest` 3x to confirm the original collision is gone.

## Phase 2: CSS Foundation

- [x] 2.1 Append `@utility bg-brand-weave` and `@utility bg-hatch` to `resources/css/app.css` (exact gradient values from design D1). `@theme` block stays untouched.

## Phase 3: New Leaf Components

- [x] 3.1 Create `resources/views/components/site/team-crest.blade.php` (D4): `Storage::disk('public')->url($team->crest_path)` when set, neutral `bg-surface-muted` fallback span, `alt=""`, no text/initials.
- [x] 3.2 Create `resources/views/components/site/player-avatar.blade.php` (D5): circular `bg-surface-muted` placeholder, unused `:player` prop, comment naming the future external-API swap edit.

## Phase 4: Layout Shell (header / nav / footer)

- [x] 4.1 Modify `resources/views/components/layouts/site.blade.php`: sticky header, two-tone wordmark (D2), `@php $nav` array + `routeIs()` active-underline loop (D3).
- [x] 4.2 Same file: `<body>` → `bg-brand-weave text-ink` (was `bg-ink`/`text-white`); `<header>`/`<footer>` keep `bg-ink`.
- [x] 4.3 Same file: expand footer to 3-column dark layout, neutral labels (`COMPETICIÓN` / `CLUBES` / `SITIO`), real routes only. **CONSTRAINT: no literal division names** ("Segunda"/"Primera") — guards `StandingsPageTest::test_empty_division_renders_no_table`'s `assertDontSee('Segunda')`.
- [x] 4.4 **CONSTRAINT**: keep header/nav/wordmark digit-free, no `2026/27`-style season label — guards `FixturesPageTest::test_page_groups_games_by_matchday`'s `assertSeeInOrder(['1','2'])` (currently passes only via `initial-scale=1` preceding the first literal "2").
- [x] 4.5 Regression: run `StandingsPageTest` + `FixturesPageTest` after 4.1–4.4.

## Phase 5: Component Restyles

- [x] 5.1 Modify `resources/views/components/site/standings-table.blade.php`: card shell, mono header row, `<x-site.team-crest>` in team cell, hover rows, brand PTS, static legend row (D7 — `CLASIFICACIÓN`/`DESCENSO` dot+label spans, no row coloring/thresholds).
- [x] 5.2 Modify `resources/views/components/site/game-card.blade.php`: `bg-surface-alt` card, mono `JORNADA N` meta line, stacked home/away rows each with `<x-site.team-crest>` + score.
- [x] 5.3 Modify `resources/views/components/site/scorer-list.blade.php`: `<x-site.player-avatar>` + stacked name/team, brand-coloured goal count.
- [x] 5.4 Modify `resources/views/components/site/news-card.blade.php`: mono brand eyebrow tag, `bg-hatch` slot when `cover_path` is null.
- [x] 5.5 Regression: run `FixturesPageTest`, `ScorersPageTest`, `NewsPageTest` after 5.1–5.4.

## Phase 6: Page Headings + `text-ink` Audit

- [x] 6.1 `resources/views/site/standings.blade.php`: heading → `text-ink`.
- [x] 6.2 `resources/views/site/fixtures.blade.php`: heading → `text-ink`, mono matchday section labels.
- [x] 6.3 `resources/views/site/scorers.blade.php`: heading → `text-ink`.
- [x] 6.4 `resources/views/site/news/index.blade.php`: heading → `text-ink`.
- [x] 6.5 `resources/views/site/news/show.blade.php`: heading → `text-ink`; article body wrapped in a `bg-surface` card for legibility on yellow.
- [x] 6.6 **Verification task**: audit all 5 headings above (plus layout `h1`/`h2` if any) — confirm every single one uses `text-ink`, none left on `text-white`. Design flagged this as the single highest-probability defect.

## Phase 7: Storage Disk Fix (approved, in-scope)

- [x] 7.1 `resources/views/site/news/index.blade.php` and `news/show.blade.php`: replace bare `Storage::url($item->cover_path)` with `Storage::disk('public')->url($item->cover_path)`, matching `team-crest`'s explicit-disk pattern.

## Phase 8: Full-Suite Checkpoint

- [x] 8.1 `docker compose exec app php artisan test` — target **169/169** (165 existing + 4 new `MatchdayFactoryTest`). Result: 169/169 passed (458 assertions).
- [x] 8.2 Manual page-by-page review of all 4 pages against the mockup source (`Amazon Superleague.dc.html`) — no pixel-diff tooling (locked decision). Done by fetching the live rendered HTML for `/`, `/partidos`, `/goleadores`, `/noticias` and reading the actual output (see apply-progress.md for findings, including a real dark-on-dark defect found and fixed).
