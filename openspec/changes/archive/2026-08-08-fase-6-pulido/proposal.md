# Proposal: Fase 6 — Pulido (Amazon Superleague visual pass)

**Roadmap phase**: Fase 6 (final) — "Diseño (integrar el frontend de Amazon Superleague),
tests y ajustes."

## Intent

Fase 5 delivered a functionally complete public site that is visually generic: plain Tailwind
utilities over a design system already fully wired. The `@theme` tokens in
`resources/css/app.css` match the saved mockup exactly; the markup never used them. This
phase closes that gap and clears the one defect deferred across three prior phases.

## Scope

### In Scope

- Layout shell: sticky header, two-tone logo, active-route underline, diagonal
  `repeating-linear-gradient` background, fuller dark footer.
- The four public pages, via their existing component seams (`standings-table`, `game-card`,
  `scorer-list`, `news-card`): card treatment, row layout, crest/avatar placeholders, mono
  meta lines, eyebrow tags, promotion/relegation legend on standings.
- Fix `database/factories/MatchdayFactory.php`: `number` uses `numberBetween(1, 18)` with no
  per-season uniqueness, intermittently breaking `GamesRelationManagerTest`. Sequence-based
  unique number plus a regression test.

### Out of Scope

- "Forma reciente" chips and "partido destacado" widget — functional additions needing new
  service/query logic (user-locked as future extensions, not polish).
- Featured-news hero card — requires a new `News` schema flag.
- Live-match badge (no status concept on `Game`), Fantasy Superleague section.
- Admin panel (Filament compiles its own stylesheet, no `viteTheme()` registered).
- Login / language-switcher UI; any schema, service or controller change.
- Recreating the mockup's single-page dashboard — the merged four-page routing stands.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `public-views`: standings table gains a promotion/relegation zone legend. **Conditional** —
  see Q2. If the legend is a static key only, this becomes None and no delta spec is needed.

## Approach

Apply the mockup's visual *language* in place, page by page, through the components Fase 5
built for this handoff. Markup and classes only. Existing HTTP tests assert content, never
DOM or classes, so they act as free regression guards; fidelity is verified by manual review
against the mockup — no snapshot or pixel-diff tooling (no precedent, disproportionate here).

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `resources/views/components/layouts/site.blade.php` | Modified | Header, nav, background, footer |
| `resources/views/site/**` (5 views) | Modified | Page-level spacing and section framing |
| `resources/views/components/site/**` (4 components) | Modified | Card, row, avatar, tag restyle |
| `resources/css/app.css` | Modified | Gradient/base layer only; `@theme` untouched |
| `database/factories/MatchdayFactory.php` | Modified | Per-season unique `number` |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Restyle breaks an `assertSee` string | Low | Full suite after each surface |
| Scope creep into functional widgets | Med | Non-goals are user-locked and explicit |
| Factory fix breaks tests relying on random numbers | Low | Full suite; sequence stays 1..N |
| "Done" is subjective without pixel tooling | Med | Page-by-page review vs mockup |

## Rollback Plan

Pure presentation plus one factory change. Revert the branch — no migrations, data, or
schema. The factory fix is independently revertible from the restyle commits.

## Dependencies

- None. Tokens, fonts, components and routes already exist.

## Success Criteria

- [ ] Each page visually reflects the mockup's card, table, spacing and colour language.
- [ ] Diagonal gradient background present; crest/avatar placeholders where the mockup shows them.
- [ ] Promotion/relegation legend renders on standings.
- [ ] Full suite green: 165 existing + 1 new regression test.
- [ ] `MatchdayFactory` collision gone, confirmed by repeated runs of `GamesRelationManagerTest`.
- [ ] No change under `app/`, `database/migrations/`, or the Filament panel.

## Open Questions

1. Standings keeps its full stat columns (PJ/G/E/P/GF/GC/DG/PTS) rather than the mockup's
   condensed POS/EQUIPO/PJ/DG/PTS — confirm?
2. The promotion/relegation legend implies zone thresholds, which are a business rule absent
   from the domain. Static key only, or configurable/hardcoded top-N / bottom-N zone colouring?
3. Crest and avatar placeholders: neutral shapes only, or wire the existing storage-backed
   image fields where present?
