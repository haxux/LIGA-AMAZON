# Design: Fase 6 — Pulido (Amazon Superleague visual pass)

## Technical Approach

Markup-and-classes only. Every token already exists in `resources/css/app.css`'s `@theme`
(verified: Barlow / Barlow Condensed / IBM Plex Mono, `--color-brand`, `--color-ink`,
`--color-surface{,-alt,-muted}`, `--color-win{,-soft}`, `--color-draw`, `--color-loss`) —
nothing is added there. The only CSS additions are two `@utility` rules for the mockup's
two `repeating-linear-gradient` textures, which are *compositions*, not tokens.

Exact values are taken from the mockup source `Amazon Superleague.dc.html`, not from
`NOTES.md` (which only describes the gradient in prose). `uploads/pasted-*.png` is a
low-fidelity wireframe render — **reference only, not a usable asset**; no logo/crest/avatar
image ships with this change.

Fase 5's four component seams (`standings-table`, `game-card`, `scorer-list`, `news-card`)
absorb all page-level restyling. Two new leaf components are added for image placeholders
so the crest fallback is written once, not three times.

## Architecture Decisions

### D1 — Gradient as `@utility`, not inline style or `@layer base`

| Option | Tradeoff |
|---|---|
| Inline `style=""` on `<body>` | Zero CSS, but a 300-char attribute in Blade; unreusable |
| `@layer base { body {...} }` | Couples the texture to `body`; the hatch variant needs a class anyway |
| **`@utility` (chosen)** | Tailwind v4 native, reusable, keeps `@theme` untouched per proposal |

```css
/* resources/css/app.css — appended after @theme */
@utility bg-brand-weave {
    background-color: var(--color-brand);
    background-image:
        repeating-linear-gradient(115deg, rgba(0,0,0,0.045) 0px, rgba(0,0,0,0.045) 1px, transparent 1px, transparent 46px),
        repeating-linear-gradient(25deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 1px, transparent 1px, transparent 78px);
}

@utility bg-hatch {
    background-image:
        repeating-linear-gradient(45deg, rgba(255,255,255,0.05) 0px, rgba(255,255,255,0.05) 8px, transparent 8px, transparent 16px);
}
```

`bg-brand-weave` goes on `<body>` (the mockup's outermost wrapper), replacing `bg-ink`.
`<header>` and `<footer>` keep `bg-ink`. `bg-hatch` fills empty image slots
(`bg-surface-muted bg-hatch`).

**Consequence (must not be missed): body text colour inverts.** `<body>` becomes
`text-ink`, and the five `<h1>`/`<h2>` page headings currently inheriting `text-white`
must become `text-ink`. Text *inside* dark cards stays white. Any element left on the
default inherits ink and reads correctly on yellow.

### D2 — Wordmark is pure typography

No logo image exists in the repo and none is being created. Two `<span>`s, `font-display`,
`AMAZON` at `font-extrabold text-brand` + `SUPERLEAGUE` at `font-semibold text-white
tracking-[0.14em]`, `text-[27px] leading-none`, baseline-aligned. Confirmed safe: no test
asserts `Liga Amazon` or any nav label (grepped `tests/`). `config('app.name')` still backs
`<title>`; it is not touched.

### D3 — Active nav via `request()->routeIs()`

A `@php $nav = [...] @endphp` array in the layout, looped. Match patterns: `site.standings`,
`site.fixtures`, `site.scorers`, and `site.news.*` (so `/noticias/{slug}` also highlights).
Active = `border-b-[3px] border-brand text-white`; inactive = `border-b-[3px]
border-transparent text-white/72`. Rejected: passing an `$active` prop from each view —
five call sites to keep in sync for zero benefit.

### D4 — One `team-crest` component, not three inline conditionals

`crest_path` uploads go to `disk('public')`, `directory('crests')` (`TeamForm.php:37-41`).
The read side therefore uses **`Storage::disk('public')->url(...)` explicitly**, not the
bare `Storage::url()` used by the news views — the default disk is `local`
(`config/filesystems.php:16`, `FILESYSTEM_DISK` unset), which resolves to the same URL
string only by coincidence of the `public/storage` symlink. Being explicit satisfies the
spec delta's "via the public disk" wording and cannot silently break.

```blade
{{-- resources/views/components/site/team-crest.blade.php --}}
@props(['team', 'size' => 'size-[22px]'])

@if ($team->crest_path)
    <img src="{{ Storage::disk('public')->url($team->crest_path) }}" alt=""
         class="{{ $size }} shrink-0 rounded-[3px] object-cover">
@else
    <span class="{{ $size }} shrink-0 rounded-[3px] bg-surface-muted" aria-hidden="true"></span>
@endif
```

Fallback is a neutral shape with **no text** (no initials): it keeps zero new strings out of
the rendered HTML, so no `assertSee`/`assertDontSee` surface is added. `alt=""` because the
team name is always adjacent — the image is decorative.

### D5 — `player-avatar` component as the swap seam (no fake field)

Rejected `$player->avatar_url ?? null`: on an Eloquent model a missing attribute silently
returns `null`, so the branch is permanent dead code that *lies* about the schema — a
reviewer cannot tell whether the column exists. Instead, a one-file component that today
renders only the circle, carrying a comment naming the exact future edit:

```blade
{{-- resources/views/components/site/player-avatar.blade.php --}}
{{-- Swap seam (Fase 6 design D5): when player avatars arrive from the external API,
     this file is the ONLY edit — wrap the span in @if with the resolved image URL.
     No avatar column exists today, so no conditional is written yet. --}}
@props(['player'])

<span {{ $attributes->merge(['class' => 'size-8 shrink-0 rounded-full bg-surface-muted']) }}
      aria-hidden="true"></span>
```

The `:player` prop is accepted now and unused — that is the seam. Cost: one unused prop.

### D6 — `MatchdayFactory`: DB-derived number + `Sequence` offset

The root cause is two-fold, and a `Sequence` alone (PlayerFactory's pattern) fixes only half:

| Failure mode | Sequence alone | DB `max()` alone | Chosen: both |
|---|---|---|---|
| Two separate `->create()` calls, one season (the actual `GamesRelationManagerTest` bug) | ✗ each new factory instance restarts at index 0 | ✓ | ✓ |
| One `->count(N)->create()` batch | ✓ | ✗ `make()` builds all N before `store()` persists any, so `max()` returns the same value N times | ✓ |

Rejected `fake()->unique()->numberBetween(1,18)`: Laravel's `fake()` is a process-wide
singleton whose unique-store is **not** reset by `RefreshDatabase`, so it throws
`OverflowException` after 18 matchdays across the whole suite, and it enforces *global*
uniqueness where the constraint is *per-season*.

```php
// definition() — keeps the shape self-documenting, mirroring PlayerFactory
'season_id' => Season::factory(),
'number' => fn (array $attributes) => self::nextNumberForSeason($attributes['season_id']),
'date' => fake()->dateTimeBetween('-2 months', '+4 months')->format('Y-m-d'),

/**
 * The DB lookup carries uniqueness ACROSS separate create() calls; the sequence
 * index carries it WITHIN one ->count(N)->create() batch, where every instance is
 * built before any is persisted. Mirrors PlayerFactory::configure()'s precedent.
 */
public function configure(): static
{
    return $this->sequence(fn (Sequence $sequence) => [
        'number' => fn (array $attributes) => self::nextNumberForSeason($attributes['season_id'], $sequence->index),
    ]);
}

private static function nextNumberForSeason(int|string $seasonId, int $offset = 0): int
{
    return (int) Matchday::where('season_id', $seasonId)->max('number') + 1 + $offset;
}
```

Why this is safe for every existing caller:
- `season_id` is key 0 in `definition()`; `Factory::expandAttributes()` writes each resolved
  value back into `$definition` before evaluating the next key, so the closure always sees a
  resolved id — for a bare `Matchday::factory()` (auto `Season::factory()`), for
  `->for($season)` (parent resolvers merge before states, preserving key order), and for
  `create(['season_id' => $id])`.
- The 8 call sites that pass `'number' => N` explicitly (`FixturesPageTest`,
  `MatchdayResourceTest`, `DatabaseSeeder:71`) override the state entirely — the closure
  never runs, so the seeder adds **zero** extra queries.
- The 15 call sites that omit `number` now get `1, 2, 3, …` instead of random — strictly
  more deterministic.

### D7 — Standings stays stacked sections; no tab JS

Locked: full column set kept, divisions stay as stacked `<section>`s. The mockup's
Primera/Segunda tab switcher is client state with no server equivalent here and is **not**
built — `resources/js/app.js` stays untouched. The legend is static decoration only:
two `<span>` dots (`bg-brand`, `bg-loss`) with mono labels `CLASIFICACIÓN` and `DESCENSO`,
placed in a `<tfoot>`-style row under the table. **No row colouring, no threshold logic.**

## Data Flow

No change. Controllers → Services → view data is untouched.

    Controller ──→ Service ──→ view(data) ──→ x-layouts.site ──→ x-site.{component}
                                                                        │
                                                       (new) x-site.team-crest / player-avatar
                                                                        │
                                                       Storage::disk('public')->url(crest_path)

`crest_path` is a column on already-loaded `Team` models (`FixturesController` eager-loads
`games.homeTeam`/`games.awayTeam`; `StandingsService` rows carry `->team`). **No new query,
no N+1.**

## File Changes

| File | Action | Description |
|---|---|---|
| `resources/css/app.css` | Modify | Append `@utility bg-brand-weave` + `@utility bg-hatch`; `@theme` untouched |
| `resources/views/components/layouts/site.blade.php` | Modify | Sticky header, two-tone wordmark, `routeIs()` nav underline, `bg-brand-weave` body + `text-ink`, 3-column dark footer |
| `resources/views/components/site/team-crest.blade.php` | **Create** | Crest image with neutral-shape fallback (D4) |
| `resources/views/components/site/player-avatar.blade.php` | **Create** | Circular placeholder, external-API swap seam (D5) |
| `resources/views/components/site/standings-table.blade.php` | Modify | Card shell `rounded-md`, mono `text-[10px] tracking-[0.1em]` header row, `<x-site.team-crest>` in the team cell, `font-display text-[19px]` team names, `hover:bg-white/[0.045]` rows, brand PTS, static legend row |
| `resources/views/components/site/game-card.blade.php` | Modify | `bg-surface-alt rounded-[5px] border-t-[3px] border-brand p-4 hover:bg-surface-muted`, mono meta line (`JORNADA N`), stacked home/away rows each with crest + score |
| `resources/views/components/site/scorer-list.blade.php` | Modify | `<x-site.player-avatar>` + stacked `font-display` name / mono team, `font-display text-[22px] text-brand` count |
| `resources/views/components/site/news-card.blade.php` | Modify | Mono brand eyebrow tag, `bg-hatch` slot when `cover_path` is null, `font-display` title, mono date |
| `resources/views/site/standings.blade.php` | Modify | `text-ink` heading + mono subtitle |
| `resources/views/site/fixtures.blade.php` | Modify | `text-ink` heading, matchday sections as mono labels, card grid |
| `resources/views/site/scorers.blade.php` | Modify | `text-ink` heading |
| `resources/views/site/news/index.blade.php` | Modify | `text-ink` heading; grid spacing |
| `resources/views/site/news/show.blade.php` | Modify | Article body moved into a `bg-surface` card for legibility on yellow |
| `database/factories/MatchdayFactory.php` | Modify | D6 |
| `tests/Feature/MatchdayFactoryTest.php` | **Create** | D6 regression suite |

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Regression (existing) | 165 tests unchanged | Verified by reading all four page tests: they assert only status codes and content strings — **no CSS class, DOM or snapshot assertion exists**. No test file changes. |
| Unit-ish (new, TDD) | `MatchdayFactory` per-season uniqueness | RED/GREEN, `tests/Feature/MatchdayFactoryTest.php` |
| Visual fidelity | Mockup parity | Manual page-by-page review. No pixel/snapshot tooling (locked). |

**Two existing assertions the restyle can actually break — hard constraints, not theory:**

1. `StandingsPageTest::test_empty_division_renders_no_table` → `assertDontSee('Segunda')`.
   The mockup footer links "Primera División" / "**Segunda** División". **The expanded footer
   MUST NOT contain division names.** Use neutral columns instead: `COMPETICIÓN`
   (Clasificación / Partidos), `CLUBES` (Goleadores), `SITIO` (Noticias) — real routes only,
   no dead `#` links, no login/language block (out of scope).
2. `FixturesPageTest::test_page_groups_games_by_matchday` → `assertSeeInOrder(['1', '2'])`.
   Passes today because `initial-scale=1` in the head precedes any `2`. Any new digit in the
   `<head>` or header must not introduce a `2` before that. Keep the wordmark/nav digit-free
   (it is) and do not add a season label like `2026/27` to the header.

The legend labels `CLASIFICACIÓN` / `DESCENSO` are new strings on `/`; no test asserts
`assertDontSee` on either. Safe.

**`MatchdayFactory` RED/GREEN cycle** — all four are deterministic, no faker seeding:

| Test | RED mechanism today |
|---|---|
| `test_matchdays_created_one_at_a_time_for_one_season_get_unique_numbers` — loop 20 `create(['season_id' => $s->id])`, assert `distinct()->count('number') === 20` | Pigeonhole: 20 draws from `numberBetween(1,18)` **must** repeat → `UniqueConstraintViolationException`. Guaranteed fail, not probabilistic. |
| `test_a_batch_created_in_one_call_gets_unique_numbers` — `->count(20)->create(['season_id' => $s->id])` | Same pigeonhole; also guards D6's batch half |
| `test_numbers_restart_per_season` — two seasons, assert both hold `number === 1` | Guards against a global counter regression |
| `test_an_explicitly_passed_number_is_respected` — `create(['number' => 7])` | Guards the 8 existing callers that pass `number` |

This is 4 tests, not the proposal's "+1" — success criterion should read *165 + 4 = 169*.
Full `php artisan test` after each surface, plus a repeated run of `GamesRelationManagerTest`
as the original-symptom check.

## Migration / Rollout

No migration. No schema, service, controller, route or JS change. Revert the branch to roll
back; the factory commit is independently revertible from the restyle commits.

## Future-Proofing Check

| Deferred item | Foreclosed? | Why |
|---|---|---|
| `recentForm()` W/D/L chips | No | The standings row is a `<tr>`; a `FORMA` `<th>`/`<td>` pair appends without touching the other cells. `--color-win/-draw/-loss` tokens already exist, unused, waiting. |
| "Partido destacado" widget | No | `game-card` is a self-contained component; a highlighted variant is a new prop or a sibling component, not a rewrite. |
| Player avatars from external API | No | D5 — one file, one edit, prop already accepted. |
| Promotion/relegation zone colouring | No | The legend markup already names both zones; adding `<tr>` classes later needs only a threshold source. |
| Featured-news hero | No | `news-card` unchanged in signature; a hero variant is additive once a `News` flag exists. |

## Work-Unit Recommendation for `sdd-tasks`

**One work unit, one branch** (`fase-6/1-pulido`), against the auto-chain /
stacked-to-main strategy. Rationale: 15 files, ~12 of them ≤40 changed lines; the surfaces
share the layout's colour inversion (D1), so splitting them creates half-restyled
intermediate states that cannot be reviewed or merged independently. Forecast:
**400-line budget risk: Medium** (estimate 380–460 changed lines, dominated by
`standings-table` and `site.blade.php`). If `sdd-tasks` forecasts >400, the only clean
split is `unit 1: MatchdayFactory fix + tests` (~70 lines, independently valuable and
independently revertible) then `unit 2: restyle` — split on that seam, not between pages.

## Open Questions

None blocking. One judgement call flagged for `sdd-apply`: `news/index.blade.php` and
`news/show.blade.php` use bare `Storage::url($item->cover_path)` while uploads go to the
`public` disk (`NewsForm.php:31-35`). It works only via the `public/storage` symlink
coincidence. Aligning them to `Storage::disk('public')->url()` is a 2-line consistency fix
in files already being edited — **recommended, but strictly outside the locked scope**; skip
it if the reviewer prefers a zero-drift diff.
