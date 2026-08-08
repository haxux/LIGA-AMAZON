# Design: Fase 5 — Parte pública (+ 4 domain extensions)

> Dependencies read: `proposal.md`, `exploration.md`, `state.yaml` (all `decisions_locked` treated as
> final and NOT re-litigated), plus all four delta specs, which `sdd-spec` completed in parallel
> mid-design: `specs/public-views/`, `specs/league-data-model/`, `specs/league-standings/`,
> `specs/admin-league-crud/`. Every spec scenario is mapped to a concrete test in the Testing
> Strategy, and the three points where design and spec disagree are reconciled explicitly in
> **Spec Reconciliation** below.

## Spec Reconciliation

| # | Point | Resolution |
|---|---|---|
| 1 | `state.yaml` flags that `sdd-spec` wrote **one** new capability, `public-views`, where `proposal.md` listed two (`public-league-site` + `player-scoring-stats`), and asks design to confirm | **Confirmed: keep `public-views` as written.** `player-scoring-stats` would be a capability whose entire surface is two methods on one service consumed by one page; the goleadores requirements read correctly inline. `sdd-archive` merges one new spec folder, not two. |
| 2 | `admin-league-crud` requires the `GameEventsRelationManager` player Select to scope "reusing `GameForm`'s existing `Get`-based filtering pattern" | **The requirement's *mechanism* clause is not implementable as written; its *scenario* is, and is what this design satisfies.** In a RelationManager there is no `home_team_id` field in the schema for `Get` to read — it returns `null`. The game is the **owner record**. See D8: `$this->getOwnerRecord()` is the RM equivalent of the same intent, and the spec's own scenario ("only players belonging to Team A or Team B are selectable") passes unchanged. Recommend `sdd-tasks` amend that one clause to the mechanism-free wording before archive. |
| 3 | `public-views` is silent on a season that has **teams but zero divisions** | Design adds a fallback (D6). This is an **addition beyond the spec**, not a contradiction — flagged as the one open question below. Either `sdd-spec` adds a scenario, or the user vetoes it and the page renders empty. |

## Technical Approach

Five additive migrations, three new models, one new service pair, four new/extended Filament surfaces,
and a public Blade site — all layered so that **nothing already shipped changes behavior**. Every piece
mirrors an existing precedent in this codebase rather than introducing a new idiom:

| New thing | Mirrors |
|---|---|
| `Season::booted()` single-current guard | `Game::booted()` `saving` hook (Fase 2/D2) |
| `News.cover_path` upload | `TeamForm`'s `crest_path` `FileUpload` |
| `GoalscorersService` + `ScorerRow` | `StandingsService` + `StandingRow` (Fase 4/D1-D4) |
| `DivisionResource::deleteAction()` | `TeamResource::deleteAction()` `QueryException` → notification |
| `GameEventsRelationManager` | `PlayersRelationManager` + `GameForm`'s dynamic Select scoping |
| Public controllers | Laravel thin controllers; services auto-wired (Fase 4/D2 kept the service non-static exactly for this) |

The composition chain locked in `state.yaml` — *active season → divisions with ≥1 team →
`forDivision()` per table* — gets **one** home (`SeasonResolver` + an abstract `SiteController`), not
four copies across route handlers.

---

## Architecture Decisions

### D1 — Migration set: 5 files, strict dependency order, all additive

| # | File (`database/migrations/`) | Creates |
|---|---|---|
| 1 | `2026_08_08_000001_add_is_current_to_seasons_table.php` | `seasons.is_current` |
| 2 | `2026_08_08_000002_create_divisions_table.php` | `divisions` |
| 3 | `2026_08_08_000003_add_division_id_to_teams_table.php` | `teams.division_id` |
| 4 | `2026_08_08_000004_create_news_table.php` | `news` |
| 5 | `2026_08_08_000005_create_game_events_table.php` | `game_events` |

`divisions` must precede `teams.division_id` (FK target). Everything else is order-independent; the
numbering keeps `php artisan migrate:fresh` deterministic.

```php
// 1 — seasons.is_current
Schema::table('seasons', function (Blueprint $table) {
    $table->boolean('is_current')->default(false)->after('name');
});
// down(): $table->dropColumn('is_current');

// 2 — divisions
Schema::create('divisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('season_id')->constrained()->cascadeOnDelete();   // locked: single-owner, same as teams/matchdays
    $table->string('name');
    $table->timestamps();

    $table->unique(['season_id', 'name']);                              // locked
});

// 3 — teams.division_id
Schema::table('teams', function (Blueprint $table) {
    $table->foreignId('division_id')->nullable()->after('season_id')
        ->constrained()->restrictOnDelete();                            // locked: forces explicit reassignment
});
// down(): $table->dropConstrainedForeignId('division_id');

// 4 — news
Schema::create('news', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();  // locked: tagged, not owned
    $table->string('title');
    $table->string('slug')->unique();                                   // locked
    $table->text('body');
    $table->string('cover_path')->nullable();
    $table->dateTime('published_at')->nullable();                       // locked: null OR future = hidden
    $table->timestamps();

    $table->index('published_at');                                      // drives the public listing filter+sort
});

// 5 — game_events
Schema::create('game_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('game_id')->constrained()->cascadeOnDelete();     // locked
    $table->foreignId('player_id')->constrained()->cascadeOnDelete();   // locked: deliberate divergence from Game's restrictOnDelete
    $table->string('type');                                             // locked: NOT a DB enum
    $table->unsignedTinyInteger('minute')->nullable();
    $table->timestamps();

    $table->index(['type', 'player_id']);                               // drives the leaderboard aggregate
});
```

**All FK choices verified against `state.yaml` line-by-line**: `divisions.season_id` cascade ✓,
`teams.division_id` restrict ✓, `news.team_id` nullOnDelete ✓, `game_events.game_id` cascade ✓,
`game_events.player_id` cascade ✓.

`minute` is `unsignedTinyInteger` (0–255), matching `players.shirt_number`'s precedent — ample for
football (extra time tops out around 120) and the narrowest honest type.

The two non-unique indexes are the only departure from Fase 2, which added unique indexes only. Both
are justified by a query this change actually ships (§4, §6). Rejected: leaving them out — a full
table scan per public page load is a needless default at zero cost to add.

### D2 — `Season::booted()` guard: unset-others, not reject

```php
/**
 * Entity invariant: at most one season is flagged current. Enforced here (not a
 * partial unique index) for the same reason as Game's guard — every write path
 * goes through Eloquent, and a partial index would reintroduce the SQLite-vs-MySQL
 * divergence Fase 2/D5 already hit.
 *
 * This guard RESOLVES rather than REJECTS: marking a season current silently
 * unsets every other. The admin never sees a validation error (D8).
 *
 * NOTE: DatabaseSeeder uses WithoutModelEvents, which mutes this guard during
 * seeding (see D9) — the seeder sets is_current directly on the single season
 * it creates.
 */
protected static function booted(): void
{
    static::saving(function (Season $season): void {
        if (! $season->is_current) {
            return;
        }

        static::query()
            ->when($season->exists, fn (Builder $query) => $query->whereKeyNot($season->getKey()))
            ->where('is_current', true)
            ->update(['is_current' => false]);
    });
}
```

Structurally identical to `Game::booted()`: one `static::saving` closure, typed parameter, early
return, doc block naming the invariant and the `WithoutModelEvents` caveat.

- `->update()` on the **query builder** issues a mass update that fires **no** model events — no
  recursion into this same hook. This is load-bearing.
- `->when($season->exists, …)`: on create there is no key yet, so the exclusion must be skipped.
  Without the guard, `whereKeyNot(null)` would silently match nothing on some drivers.
- `$season->is_current` reads through the `'is_current' => 'boolean'` cast, so a Filament Toggle's
  `'1'`/`'0'` string arrives as a real bool — the same string-vs-int trap `Game`'s `(int)` cast
  documents.
- **No transaction wrapping** (locked: single-trusted-operator demo scope).

| Option | Tradeoff | Verdict |
|---|---|---|
| Partial unique index `WHERE is_current = 1` | Not portable to the SQLite test runner (Fase 2/D5) | Rejected |
| Reject the save with `ValidationException` | Forces a two-step "unset A, then set B" admin dance | Rejected |
| **`saving` hook that unsets the others** | One save, always consistent, mirrors the shipped precedent | **Chosen** |

### D3 — `StandingsService::forDivision()` as a sibling, with one shared private fold

`forDivision()` is a **new public method on the existing class** — a new class or a rewrite of
`forSeason()` are both off the table (locked). The open question is the fold body.

| Option | Tradeoff | Verdict |
|---|---|---|
| Duplicate the 25-line accumulator/sort into `forDivision()` | `forSeason()` stays byte-identical, but the home/away symmetry and the tie-break now exist twice and can silently drift | Rejected |
| Extract a private `buildTable(Collection $roster, int $seasonId)` used by both | `forSeason()`'s **body** shrinks to a 4-line delegation; its signature, return type, ordering and every observable behavior are unchanged, pinned by Fase 4's 12 existing tests | **Chosen** |

```php
public function forSeason(Season $season): Collection
{
    return $this->buildTable(
        Team::query()->where('season_id', $season->getKey())->orderBy('id')->get(),
        $season->getKey(),
    );
}

/** @return Collection<int, StandingRow> ordered points desc, goal_difference desc, goals_for desc */
public function forDivision(Division $division): Collection
{
    return $this->buildTable(
        Team::query()->where('division_id', $division->getKey())->orderBy('id')->get(),
        $division->season_id,
    );
}

private function buildTable(Collection $teams, int $seasonId): Collection
{
    // ...verbatim body of today's forSeason(), with the roster query hoisted out
    // and $season->getKey() replaced by $seasonId. accumulate() is untouched.
}
```

Both methods query **the same season-scoped game set**; only the roster (row universe) narrows. Fase
4/D5's `isset($rows[$teamId])` guard in `accumulate()` then does the rest for free: a cross-division
game credits only the in-division side. No new filtering logic is needed, and no `division_id` clause
touches the games query.

**Explicit note for review**: the extraction is a REFACTOR step (RED → GREEN → REFACTOR), performed
only after `forDivision()`'s own tests are green, with Fase 4's suite as the regression pin. If the
reviewer prefers `forSeason()` untouched down to the character, the fallback is the rejected
duplication option — behavior is identical either way. Flagged in `risks`.

`forDivision()` takes `Division $division`, not `int $divisionId` — symmetric with `forSeason(Season)`.

### D4 — `GoalscorersService` + `ScorerRow`: same shape, one shared aggregate

```php
final class ScorerRow
{
    public function __construct(
        public readonly Player $player,   // carries ->team for the crest/short_name, eager-loaded
        public readonly int $count = 0,
    ) {}
}

final class GoalscorersService
{
    /** @return Collection<int, ScorerRow> ordered count desc; null $limit = no limit */
    public function topScorers(Season $season, ?int $limit = 10): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_GOAL, $limit);
    }

    /** @return Collection<int, ScorerRow> */
    public function topAssisters(Season $season, ?int $limit = 10): Collection
    {
        return $this->leaderboard($season, GameEvent::TYPE_ASSIST, $limit);
    }

    private function leaderboard(Season $season, string $type, ?int $limit): Collection
    {
        $counts = GameEvent::query()
            ->where('type', $type)
            ->whereHas('game.matchday', fn (Builder $query) => $query->where('season_id', $season->getKey()))
            ->groupBy('player_id')
            ->pluck(DB::raw('COUNT(*)'), 'player_id');   // [player_id => count]

        $rows = Player::query()
            ->whereIn('id', $counts->keys())
            ->with('team')
            ->orderBy('id')                              // deterministic seed order — Fase 4/D4
            ->get()
            ->map(fn (Player $player) => new ScorerRow($player, (int) $counts[$player->getKey()]))
            ->sortBy([['count', 'desc']])
            ->values();

        return $limit === null ? $rows : $rows->take($limit);
    }
}
```

Deliberate parallels with `StandingsService` (Fase 4): `final class`, no constructor, **instance**
(not static) methods so the container auto-wires them into a controller and a caching decorator stays
possible; two queries not one; a readonly VO carrying the whole model; `sortBy` with the explicit
nested-array form (a flat array can be misread as a `[class, method]` callable); `orderBy('id')` as
the stable-sort seed so full ties are reproducible; `whereHas` over `whereIn(ids)`.

Deliberate differences: the aggregate happens **in SQL** (`groupBy` + `COUNT(*)`), not in a PHP fold —
unlike standings there is no zero-row requirement (a player with no goals must NOT appear), so the
event rows alone are a complete row universe. `whereHas('game.matchday', …)` uses dot-nested relation
traversal, which is the season scope for an event.

`?int $limit = 10` **is** the documented extension point for a future "ver todos" page (locked as
out-of-scope): that page passes `null`. No other code changes.

`GameEvent::TYPE_GOAL` / `TYPE_ASSIST` / `TYPES` live on the **model**, not on a Filament form class.
This diverges from `PlayerForm::POSITIONS` on purpose: positions are admin-only vocabulary, whereas
event types are queried by a service and must not make `app/Services/` depend on `app/Filament/`.

### D5 — Public controllers: 4 single-purpose classes + one abstract base

**`App\Http\Controllers\Public\` is impossible** — `public` is a PHP reserved word and cannot be a
namespace segment (fatal parse error). Namespace is `App\Http\Controllers\Site`, views live in
`resources/views/site/`, route names are prefixed `site.`.

| Option | Tradeoff | Verdict |
|---|---|---|
| One `PublicController` with 5 actions | Fewest files, but every work-unit branch touches the same file → stacked-branch conflicts | Rejected |
| Closures in `routes/web.php` | No test seam, no DI, contradicts the thin-controller rule | Rejected |
| **4 controllers + abstract `SiteController`** | Idiomatic, `NewsController` is naturally resourceful (`index`/`show`), season resolution lives in exactly one inherited method | **Chosen** |

```php
abstract class SiteController extends Controller
{
    public function __construct(protected readonly SeasonResolver $seasons) {}

    /** The is_current → latest('id') → 404 chain, in one place for all four pages. */
    protected function activeSeason(): Season
    {
        return $this->seasons->active() ?? abort(404);
    }
}
```

```php
final class SeasonResolver          // app/Services/SeasonResolver.php
{
    public function active(): ?Season
    {
        return Season::query()->where('is_current', true)->first()
            ?? Season::query()->latest('id')->first();
    }
}
```

Domain rule (which season is "the" season) sits in the Services layer per `config.yaml`
`rules.design`; the HTTP concern (`abort(404)` when zero seasons exist) sits in the controller base.
A trait was rejected — it cannot hold the constructor-injected dependency without a boot hack.

Controllers (`__invoke` for the three single-action ones):

| Class | Action | Reads |
|---|---|---|
| `StandingsController` | `__invoke(StandingsService)` | divisions with ≥1 team → `forDivision()` each; fallback below |
| `FixturesController` | `__invoke()` | `$season->matchdays()->with('games.homeTeam','games.awayTeam')->orderBy('number')` |
| `ScorersController` | `__invoke(GoalscorersService)` | `topScorers($season)` + `topAssisters($season)` |
| `NewsController` | `index()`, `show(string $slug)` | `News::published()` listing / `firstOrFail()` by slug |

`show()` takes a plain `string $slug` and does `News::published()->where('slug', $slug)->firstOrFail()`
rather than route-model binding on `{news:slug}` — implicit binding would resolve a **draft** item and
then need a second manual `abort`, splitting the visibility rule across two places.

### D6 — Standings composition, and the zero-division fallback

```php
$divisions = $season->divisions()->has('teams')->orderBy('id')->get();
```

- `has('teams')` **is** the locked "≥1 team" filter, executed by the database. Segunda (0 teams)
  produces no row, therefore no table, therefore no placeholder — exactly as locked. It appears the
  moment an admin assigns it a team, with no code change.
- `orderBy('id')` = creation order, not `orderBy('name')`. Alphabetical only happens to order
  Primera/Segunda correctly and would break on "Cuarta"/"Tercera". A future `divisions.position`
  column is additive.

**New decision (not in `state.yaml`, and not covered by `public-views` — needs a spec scenario or a
veto):** if the active season has **zero** qualifying divisions, render a **single unnamed table** from
`forSeason($season)` instead of an empty page. Rationale: `teams.division_id` is locked as permanently
nullable, so "season with teams but no divisions" is a reachable, supported state (any season an admin
creates in the panel starts that way), and a blank homepage would read as a bug. This does not weaken
the "one table per non-empty division" requirement — it is the `$divisions->isEmpty()` branch of it,
and the spec's own scenarios (Primera renders, Segunda does not) are unaffected.

### D7 — News body as plain text

`Textarea` (10 rows) in the form; the view renders `{!! nl2br(e($news->body)) !!}` — escape first,
then add breaks. `RichEditor` rejected: it stores raw HTML that a public page must render unescaped,
which is a real XSS surface for zero demo benefit. Upgrading is a Fase 6 decision with a clean
migration path (the column is already `text`).

### D8 — Filament surfaces

**`SeasonForm`** — append:
```php
Toggle::make('is_current')
    ->label('Current season')
    ->helperText('Marking this season current automatically unmarks every other season.'),
```
**`SeasonsTable`** — append `IconColumn::make('is_current')->label('Current')->boolean()->sortable()`.

The "only one current" rule surfaces as **nothing at all**: D2 resolves the conflict inside the same
save, so there is no form error, no notification, no confirmation modal. The `helperText` is the only
UI affordance, and the `IconColumn` is the after-the-fact proof. This is the locked behavior.

**`DivisionResource`** (`app/Filament/Resources/Divisions/`, `navigationGroup = 'League'`) — standard
5-file layout. `DivisionForm`: `Select::make('season_id')->relationship('season','name')->required()
->searchable()->preload()->live()`, `TextInput::make('name')->required()->unique(ignoreRecord: true,
modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('season_id', $get('season_id')))` — the
exact `TeamForm` name-uniqueness idiom, matching the DB's `unique(season_id, name)`.
`DivisionsTable`: `name`, `season.name`, `teams_count`. Delete needs `DivisionResource::deleteAction()`
copied from `TeamResource::deleteAction()` (`restrictOnDelete` → `QueryException` → danger
notification "This division still has teams. Reassign them first." → `$action->halt()`).

**`TeamForm`** — add, after the (now `->live()`) season Select:
```php
Select::make('division_id')
    ->relationship('division', 'name', fn (Builder $query, Get $get) => $query->where('season_id', $get('season_id')))
    ->searchable()->preload(),          // nullable — no ->required()
```
`TeamsTable` — add `TextColumn::make('division.name')->label('Division')->sortable()` and a
`SelectFilter::make('division')->relationship('division','name')`.

**`NewsResource`** (`app/Filament/Resources/News/`, `navigationGroup = 'Content'`; pages `ListNews` /
`CreateNews` / `EditNews`). `NewsForm`:
```php
TextInput::make('title')->required()->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
TextInput::make('slug')->required()->unique(ignoreRecord: true),
Textarea::make('body')->required()->rows(10)->columnSpanFull(),
FileUpload::make('cover_path')->image()->disk('public')->directory('news')->visibility('public'),
DateTimePicker::make('published_at')->helperText('Leave empty to keep this a draft. A future date schedules it.'),
Select::make('team_id')->relationship('team', 'name')->searchable()->preload(),   // nullable tag
```
`cover_path` is `crest_path`'s block verbatim with `directory('news')` — locked. `NewsTable`:
`ImageColumn::make('cover_path')->disk('public')`, `title` (searchable), `team.name`, `published_at`
(dateTime, sortable), default sort `published_at` desc.

**`GameEventsRelationManager`** (`app/Filament/Resources/Games/RelationManagers/`, `$relationship =
'events'`, registered in `GameResource::getRelations()`):
```php
Select::make('player_id')
    ->relationship('player', 'name', fn (Builder $query) => $query->whereIn('team_id', [
        $this->getOwnerRecord()->home_team_id,
        $this->getOwnerRecord()->away_team_id,
    ]))
    ->getOptionLabelFromRecordUsing(fn (Player $record): string => "{$record->team->short_name} · #{$record->shirt_number} {$record->name}")
    ->required()->searchable()->preload(),
Select::make('type')->options(GameEvent::TYPES)->required()->native(false),
TextInput::make('minute')->numeric()->minValue(1)->maxValue(130),
```
**Deviation from the spec's mechanism clause, on purpose** (Spec Reconciliation #2): `GameForm` uses
`Get` because the teams are *fields in the same form*. Inside a RelationManager the game is the
**owner record**, so `$this->getOwnerRecord()` is the correct handle — `Get('home_team_id')` would
read a field that does not exist in this schema and return `null`. Same intent (scope the player list
to this game's two squads), correct mechanism; `admin-league-crud`'s scenario passes verbatim. The
`getOptionLabelFromRecordUsing` label mirrors `GameForm`'s matchday label and disambiguates duplicate
player names across the two squads.

Table: `player.name`, `player.team.short_name` (Team), `type` (badge), `minute`; header
`CreateAction`, row `EditAction`/`DeleteAction`, `defaultSort('minute')`.

### D9 — Seeder: `is_current` set directly, because the guard is muted

`DatabaseSeeder` uses `WithoutModelEvents` (Fase 2/D3). **`Season::booted()`'s new guard is therefore
muted during seeding — exactly like `Game::booted()`'s.** This is the documented known gap, not a bug
to fix here.

Consequence, stated concretely: the seeder must set `is_current` **as a plain attribute on the create
call** and cannot rely on the guard to unset anything. Because it creates **exactly one** `Season`
row, the invariant holds trivially — after `php artisan db:seed`,
`Season::where('is_current', true)->count() === 1`, asserted by a new test (§10). If a future seeder
ever creates a second season, it must set `is_current` on exactly one of them by hand, or drop
`WithoutModelEvents`. A code comment in the seeder says so.

```php
$season = Season::factory()->create(['name' => '2025/26', 'is_current' => true]);

// Locked seed: Primera holds all 10 curated clubs; Segunda exists so the concept is
// structurally enabled and visible in the panel, but is deliberately left empty —
// the public standings page simply renders no table for it until an admin adds teams.
$primera = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
Division::factory()->create(['season_id' => $season->id, 'name' => 'Segunda']);

$teams = collect(TeamFactory::CLUBS)
    ->map(fn (array $club, string $name) => Team::factory()->create([
        'season_id' => $season->id,
        'division_id' => $primera->id,          // ← only new line in this block
        'name' => $name,
        'short_name' => $club['short'],
        'crest_path' => null,
    ]))
    ->values();
```

`TeamFactory::CLUBS` is unchanged (its "Fase-5 mockup division A" comment now literally means Primera
— worth updating the comment, nothing else). No news or game_events are seeded: both are admin-entered
content, and seeding fake goals would make the leaderboard meaningless. **Documented consequence**: on
a fresh seed the goleadores page renders empty lists and the noticias listing renders an empty state —
both pages must handle that gracefully, and their HTTP tests must build their own fixtures.

**Existing `DatabaseSeederTest` assertions are unaffected**: `Season::count()` is still 1,
`Team::count()` still 10, stadium/player/matchday/game counts untouched, `crest_path` still all-null.
Nothing regresses; three assertions are *added* (§10).

Because factories go through `fill()`, `is_current` must be in `Season`'s `#[Fillable]` and
`division_id` in `Team`'s — otherwise the seeder's attributes are silently dropped. `SeasonFactory`
gains `'is_current' => false` in `definition()` plus a `current()` state for tests.

### D10 — Design tokens and font loading

Laravel 13 already ships the mechanism: `vite.config.js` declares fonts via
`laravel-vite-plugin/fonts`' `bunny()` helper, and the layout emits them with `@fonts`. **Follow it —
do not add `<link rel="preconnect">` tags or a CSS `@import`.**

```js
// vite.config.js — replace the single Instrument Sans entry
fonts: [
    bunny('Barlow Condensed', { weights: [500, 600, 700, 800] }),
    bunny('Barlow',           { weights: [400, 500, 600, 700] }),
    bunny('IBM Plex Mono',    { weights: [400, 500] }),
],
```
Weights come straight from NOTES.md. Instrument Sans is dropped because its only consumer
(`welcome.blade.php`) is deleted in this change (D11). Bunny Fonts is a drop-in Google Fonts mirror —
all three families are available there, and it is what the framework's own scaffold uses.

```css
/* resources/css/app.css — the existing @theme block gains these; --font-sans is REPLACED */
@theme {
    --font-sans: 'Barlow', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
    --font-display: 'Barlow Condensed', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

    --color-brand: #FFB800;          /* accent: marca, PTS, CTAs, active borders */
    --color-ink: #1B1B1B;            /* header/footer/dark sections */
    --color-surface: #2B2B2B;        /* cards, table */
    --color-surface-alt: #262626;
    --color-surface-muted: #383838;  /* image placeholders */
    --color-win: #1F7A4D;            /* form chip W */
    --color-win-soft: #6FE0A8;
    --color-draw: #5C5C5C;           /* form chip D */
    --color-loss: #8A3B3B;           /* form chip L / relegation zone */
}
```
Tailwind v4 derives the utilities automatically: `font-display`, `font-mono`, `bg-brand`, `text-brand`,
`bg-ink`, `bg-surface`, `bg-win`/`bg-draw`/`bg-loss`, `border-brand`, etc. Only the two `@source`
lines above the block stay as-is.

Changing `--font-sans` does **not** affect the Filament panel: Filament v4/v5 loads its own compiled
stylesheet and never consumes `resources/css/app.css` (no custom theme is registered in this project).
Verified against `app/Providers/Filament/` having no `->viteTheme()`.

The diagonal-stripe `repeating-linear-gradient` from NOTES.md is **not** added here — that is visual
composition (Fase 6), not a token.

Also in this unit, the 1-line doc drift fix: `ARQUITECTURA.md` §2 and `openspec/config.yaml`'s
`context` block both say "Tailwind 3.x"; actual is v4.

### D11 — Deleting the stock scaffold (must not be forgotten)

`GET /` currently returns `view('welcome')`, and **`tests/Feature/ExampleTest.php` asserts
`$this->get('/')->assertStatus(200)`**. That test has no `RefreshDatabase` and no seeding, so the
moment `/` becomes the standings page it will hit a database with zero seasons and receive **404** —
a guaranteed red test that has nothing to do with the feature. Both stock artifacts are therefore
deleted in the public-site work unit and replaced by `StandingsPageTest`:

- `resources/views/welcome.blade.php` → Delete
- `tests/Feature/ExampleTest.php` → Delete (`tests/Unit/ExampleTest.php` is untouched — no HTTP)

---

## Data Flow

```
                       ┌─────────────────────────────────────────────┐
 GET /                 │ SeasonResolver::active()                    │
 GET /partidos    ────►│   is_current ─► latest('id') ─► null        │──► abort(404)
 GET /goleadores       └───────────────────┬─────────────────────────┘
 GET /noticias                             │ Season
                                           ▼
   /            ─► divisions()->has('teams')->orderBy('id')
                        │  (empty? ─► forSeason() single table — D6)
                        └─► StandingsService::forDivision()  ─► Collection<StandingRow> ─► table per division

   /partidos    ─► season->matchdays()->with(games.homeTeam, games.awayTeam)->orderBy('number')
                        └─► per game:  scores null ? fixture-card : result-card

   /goleadores  ─► GoalscorersService::topScorers($season) / ::topAssisters($season)
                        └─► game_events ⋈ games ⋈ matchdays(season)  GROUP BY player_id  ─► Collection<ScorerRow> (10)

   /noticias    ─► News::published()->latest('published_at')          (independent of Season)
   /noticias/{slug} ─► News::published()->where(slug)->firstOrFail()
```

Admin write side: `GameEventsRelationManager` (on the Game edit page) is the only writer of
`game_events`; `NewsResource` the only writer of `news`; `DivisionResource` + `TeamForm` the only
writers of `divisions`/`teams.division_id`; `SeasonForm`'s Toggle the only writer of `is_current`.

---

## File Changes

### Schema & models

| File | Action | Description |
|---|---|---|
| `database/migrations/2026_08_08_000001_add_is_current_to_seasons_table.php` | Create | D1 |
| `database/migrations/2026_08_08_000002_create_divisions_table.php` | Create | D1 |
| `database/migrations/2026_08_08_000003_add_division_id_to_teams_table.php` | Create | D1 |
| `database/migrations/2026_08_08_000004_create_news_table.php` | Create | D1 |
| `database/migrations/2026_08_08_000005_create_game_events_table.php` | Create | D1 |
| `app/Models/Season.php` | Modify | `#[Fillable]` += `is_current`; `casts()` += `'is_current' => 'boolean'`; `booted()` guard (D2); `divisions(): HasMany` |
| `app/Models/Team.php` | Modify | `#[Fillable]` += `division_id`; `division(): BelongsTo`; `news(): HasMany` |
| `app/Models/Player.php` | Modify | `gameEvents(): HasMany` |
| `app/Models/Game.php` | Modify | `events(): HasMany(GameEvent::class)` |
| `app/Models/Division.php` | Create | `#[Fillable(['season_id','name'])]`; `season()`, `teams()`. No `casts()` — nothing to cast |
| `app/Models/News.php` | Create | `#[Fillable([...])]`; `casts(): ['published_at' => 'datetime']`; `team()`; `#[Scope] published()` |
| `app/Models/GameEvent.php` | Create | `#[Fillable([...])]`; `TYPE_GOAL`/`TYPE_ASSIST`/`TYPES`; `casts(): ['minute' => 'integer']`; `game()`, `player()` |
| `database/factories/DivisionFactory.php` | Create | `season_id => Season::factory()`, `name => 'Primera'`-style |
| `database/factories/NewsFactory.php` | Create | + `draft()` and `scheduled()` states |
| `database/factories/GameEventFactory.php` | Create | + `goal()` / `assist()` states |
| `database/factories/SeasonFactory.php` | Modify | `'is_current' => false` + `current()` state |
| `database/factories/TeamFactory.php` | Modify | `'division_id' => null` in `definition()`; CLUBS comment |
| `database/seeders/DatabaseSeeder.php` | Modify | D9 |

`News` maps to the `news` table with **no `$table` override**: Laravel's inflector treats "news" as
uncountable, so `Str::snake(Str::pluralStudly('News')) === 'news'`. A schema test pins this rather
than trusting it silently.

### Services

| File | Action | Description |
|---|---|---|
| `app/Services/StandingsService.php` | Modify | `forDivision()` added; `forSeason()` delegates to the extracted private `buildTable()` (D3). `accumulate()` untouched |
| `app/Services/GoalscorersService.php` | Create | D4 |
| `app/Services/ScorerRow.php` | Create | D4 |
| `app/Services/SeasonResolver.php` | Create | D5 |

### Filament

| File | Action |
|---|---|
| `app/Filament/Resources/Seasons/Schemas/SeasonForm.php` | Modify — `Toggle` |
| `app/Filament/Resources/Seasons/Tables/SeasonsTable.php` | Modify — `IconColumn` |
| `app/Filament/Resources/Divisions/{DivisionResource,Schemas/DivisionForm,Tables/DivisionsTable,Pages/{List,Create,Edit}Division}.php` | Create (6 files) |
| `app/Filament/Resources/Teams/Schemas/TeamForm.php` | Modify — division Select, `season_id` → `->live()` |
| `app/Filament/Resources/Teams/Tables/TeamsTable.php` | Modify — division column + filter |
| `app/Filament/Resources/News/{NewsResource,Schemas/NewsForm,Tables/NewsTable,Pages/{ListNews,CreateNews,EditNews}}.php` | Create (6 files) |
| `app/Filament/Resources/Games/RelationManagers/GameEventsRelationManager.php` | Create |
| `app/Filament/Resources/Games/GameResource.php` | Modify — register the RM |

### Public site

| File | Action |
|---|---|
| `routes/web.php` | Modify — 5 routes replace the welcome closure |
| `app/Http/Controllers/Site/SiteController.php` | Create — abstract base (D5) |
| `app/Http/Controllers/Site/{Standings,Fixtures,Scorers}Controller.php` | Create — `__invoke` |
| `app/Http/Controllers/Site/NewsController.php` | Create — `index`, `show` |
| `resources/views/components/layouts/site.blade.php` | Create — `<html>`, `@fonts`, `@vite`, nav, `{{ $slot }}`, footer |
| `resources/views/components/site/{standings-table,game-card,scorer-list,news-card}.blade.php` | Create — anonymous components |
| `resources/views/site/{standings,fixtures,scorers}.blade.php` | Create |
| `resources/views/site/news/{index,show}.blade.php` | Create |
| `resources/views/welcome.blade.php` | **Delete** (D11) |
| `resources/css/app.css` | Modify — `@theme` (D10) |
| `vite.config.js` | Modify — fonts (D10) |
| `ARQUITECTURA.md`, `openspec/config.yaml` | Modify — Tailwind 3.x → 4.x |

```php
// routes/web.php — exact definitions
Route::get('/',                  StandingsController::class)->name('site.standings');
Route::get('/partidos',          FixturesController::class)->name('site.fixtures');
Route::get('/goleadores',        ScorersController::class)->name('site.scorers');
Route::get('/noticias',          [NewsController::class, 'index'])->name('site.news.index');
Route::get('/noticias/{slug}',   [NewsController::class, 'show'])->name('site.news.show');
```
URL slugs are Spanish (public-facing, matching the mockup and the audience); class, view and route
names are English (project rule). `/noticias/{slug}` is declared **after** `/noticias` so the static
segment wins — Laravel matches in declaration order.

---

## Interfaces / Contracts

```php
final class ScorerRow {
    public function __construct(public readonly Player $player, public readonly int $count = 0) {}
}

final class GoalscorersService {
    public function topScorers(Season $season, ?int $limit = 10): Collection;    // Collection<int, ScorerRow>
    public function topAssisters(Season $season, ?int $limit = 10): Collection;
}

final class StandingsService {
    public function forSeason(Season $season): Collection;      // UNCHANGED contract
    public function forDivision(Division $division): Collection;// NEW sibling, same StandingRow shape
}

final class SeasonResolver {
    public function active(): ?Season;                          // null ⇒ zero seasons exist
}

class GameEvent extends Model {
    public const TYPE_GOAL = 'goal';
    public const TYPE_ASSIST = 'assist';
    public const TYPES = [self::TYPE_GOAL => 'Goal', self::TYPE_ASSIST => 'Assist'];
}

class News extends Model {
    #[Scope] protected function published(Builder $query): void;  // published_at not null AND <= now()
}
```

Blade contracts: `<x-layouts.site>`, `<x-site.standings-table :heading :rows>`,
`<x-site.game-card :game>`, `<x-site.scorer-list :title :rows>`, `<x-site.news-card :item>`.

---

## Testing Strategy (`strict_tdd: true`)

Runner: PHPUnit 12, SQLite `:memory:`, `RefreshDatabase`, everything in `tests/Feature/`
(`tests/Unit/` boots no app — Fase 2/4 precedent).

### Spec scenario → test mapping (all 4 delta specs, 32 scenarios)

| Spec | Requirement | Scenario | Test |
|---|---|---|---|
| public-views | Active season resolution | Resolves to the flagged season | `SeasonResolverTest::test_returns_the_season_flagged_current` |
| public-views | " | Fallback to most recently created | `SeasonResolverTest::test_falls_back_to_the_latest_season_when_none_is_flagged` |
| public-views | " | Zero seasons yields 404 | `SeasonResolverTest::test_returns_null_when_no_seasons_exist` + `StandingsPageTest::test_page_returns_404_when_no_seasons_exist` |
| public-views | Standings page | Divisions with teams each render a table | `StandingsPageTest::test_standings_page_renders_a_table_per_populated_division` + `::test_empty_division_renders_no_table` |
| public-views | " | Reflects an `is_current` change | `StandingsPageTest::test_page_follows_the_current_season_flag` |
| public-views | Partidos page | Scored matchday renders result cards | `FixturesPageTest::test_played_game_shows_its_score` |
| public-views | " | Unplayed matchday renders fixture cards | `FixturesPageTest::test_unplayed_game_shows_no_score` |
| public-views | " | Games grouped by matchday | `FixturesPageTest::test_page_groups_games_by_matchday` |
| public-views | Goleadores page | Top scorers and assisters both render | `ScorersPageTest::test_page_lists_top_scorers_and_assisters` |
| public-views | " | Player with zero events absent | `ScorersPageTest::test_page_omits_players_with_no_events` |
| public-views | " | Fewer than 10 scorers renders correctly | `ScorersPageTest::test_page_renders_a_short_list_without_placeholder_rows` |
| public-views | Noticias listing | Only published, most recent first | `NewsPageTest::test_listing_shows_published_items_newest_first` |
| public-views | " | Future-dated news hidden | `NewsPageTest::test_listing_hides_drafts_and_future_items` |
| public-views | Noticias detail | Published item viewable by slug | `NewsPageTest::test_detail_page_renders_a_published_item_by_slug` |
| public-views | " | Unpublished item not viewable | `NewsPageTest::test_detail_page_returns_404_for_a_draft` |
| league-standings | `forDivision()` | Only the division's teams | `StandingsServiceTest::test_for_division_only_includes_that_divisions_teams` |
| league-standings | " | Stats include cross-division games | `::test_for_division_ignores_a_cross_division_opponent_but_credits_the_in_division_team` |
| league-standings | " | Zero-game team is an all-zero row | `::test_for_division_zero_game_team_appears_as_an_all_zero_row` |
| league-standings | " | Multi-level tie-break matches `forSeason()` | `::test_for_division_orders_by_points_then_goal_difference_then_goals_for` |
| league-standings | " | Empty division returns empty collection | `::test_for_division_returns_an_empty_collection_for_an_empty_division` |
| league-data-model | Six tables (MODIFIED) | 3 unchanged unique-constraint scenarios | `SchemaMigrationTest` — **existing methods, unchanged** (regression pin) |
| league-data-model | Single-current invariant | Marking current unsets all others | `SeasonCurrentGuardTest::test_marking_a_season_current_unsets_every_other_season` |
| league-data-model | " | Zero current seasons is valid | `SeasonCurrentGuardTest::test_saving_a_non_current_season_leaves_the_current_flag_alone` |
| league-data-model | Divisions | Division delete blocked with teams | `DeleteStrategyTest::test_deleting_a_division_with_teams_is_restricted` |
| league-data-model | " | Season delete cascades to divisions | `DeleteStrategyTest::test_deleting_a_season_cascades_to_divisions` |
| league-data-model | Game events | Game delete cascades to events | `DeleteStrategyTest::test_deleting_a_game_cascades_to_its_events` |
| league-data-model | " | Player delete cascades to events | `DeleteStrategyTest::test_deleting_a_player_cascades_to_their_events` |
| league-data-model | News | Team delete nulls `team_id` | `DeleteStrategyTest::test_deleting_a_team_nulls_its_news_team_id` |
| league-data-model | " | News can exist untagged | `SchemaMigrationTest::test_news_item_persists_with_a_null_team_id` |
| league-data-model | Demo seed | Populated + empty divisions | `DatabaseSeederTest::test_fresh_seed_creates_primera_with_all_ten_teams_and_an_empty_segunda` |
| admin-league-crud | Season toggle | Toggling unsets others inline | `SeasonResourceTest::test_toggling_is_current_unsets_the_previous_current_season_without_a_form_error` |
| admin-league-crud | " | Column visible and sortable | `SeasonResourceTest::test_is_current_column_renders_in_the_list` |
| admin-league-crud | NewsResource | Create with an image | `NewsResourceTest::test_can_create_a_news_item_with_a_cover_image` |
| admin-league-crud | " | Empty `published_at` keeps a draft | `NewsResourceTest::test_leaving_published_at_empty_persists_a_draft` |
| admin-league-crud | DivisionResource | Operator creates a division | `DivisionResourceTest::test_can_create_a_division_via_the_form` |
| admin-league-crud | " | Assign a team to a division | `TeamResourceTest::test_can_assign_a_team_to_a_division` |
| admin-league-crud | " | Team left without a division | `TeamResourceTest::test_team_persists_with_a_null_division` |
| admin-league-crud | GameEvents RM | Operator records a goal | `GameEventsRelationManagerTest::test_can_record_a_goal_via_the_relation_manager` |
| admin-league-crud | " | Player select scoped to the 2 teams | `GameEventsRelationManagerTest::test_player_select_only_offers_players_from_the_two_teams_in_the_game` |

### Full test-file inventory (spec scenarios + design-level guarantees)

| Layer | Test file | Action | Key methods |
|---|---|---|---|
| Schema | `SchemaMigrationTest.php` | Modify | `test_seasons_table_has_is_current_column`; `test_divisions_table_has_expected_columns`; `test_teams_table_has_division_id_column`; `test_news_table_has_expected_columns`; `test_news_model_maps_to_the_news_table`; `test_game_events_table_has_expected_columns`; `test_duplicate_division_name_within_same_season_is_rejected`; `test_duplicate_news_slug_is_rejected` |
| Schema | `DeleteStrategyTest.php` | Modify | `test_deleting_a_season_cascades_to_divisions`; `test_deleting_a_division_with_teams_is_restricted`; `test_deleting_a_team_nulls_its_news_team_id`; `test_deleting_a_game_cascades_to_its_events`; `test_deleting_a_player_cascades_to_their_events` |
| Model | `SeasonCurrentGuardTest.php` | Create | `test_marking_a_season_current_unsets_every_other_season`; `test_creating_a_current_season_unsets_the_previous_one`; `test_saving_a_non_current_season_leaves_the_current_flag_alone`; `test_renaming_the_current_season_keeps_it_current` (naming mirrors `GameGuardTest.php`) |
| Service | `StandingsServiceTest.php` | Modify | **All existing methods stay byte-identical** (regression pin for D3). Add: `test_for_division_only_includes_that_divisions_teams`; `test_for_division_ignores_a_cross_division_opponent_but_credits_the_in_division_team`; `test_for_division_and_for_season_agree_when_the_division_holds_every_team`; `test_for_division_orders_by_points_then_goal_difference_then_goals_for` |
| Service | `GoalscorersServiceTest.php` | Create | `test_top_scorers_counts_only_goal_events`; `test_top_assisters_counts_only_assist_events`; `test_events_from_another_season_are_excluded`; `test_players_with_no_events_are_absent`; `test_rows_are_ordered_by_count_descending`; `test_result_is_limited_to_ten_by_default`; `test_null_limit_returns_every_scorer`; `test_tied_counts_keep_a_stable_order` |
| Service | `SeasonResolverTest.php` | Create | `test_returns_the_season_flagged_current`; `test_falls_back_to_the_latest_season_when_none_is_flagged`; `test_returns_null_when_no_seasons_exist` |
| Seeder | `DatabaseSeederTest.php` | Modify | Existing 5 methods unchanged. Add: `test_fresh_seed_marks_exactly_one_season_as_current`; `test_fresh_seed_creates_primera_with_all_ten_teams_and_an_empty_segunda`; `test_fresh_seed_leaves_no_team_without_a_division` |
| Admin | `SeasonResourceTest.php` | Modify | Existing 6 methods unchanged. Add `test_toggling_is_current_unsets_the_previous_current_season_without_a_form_error` (asserts `assertHasNoFormErrors()` — proves D8's "transparent" behavior); `test_is_current_column_renders_in_the_list` |
| Admin | `DivisionResourceTest.php` | Create | list/create/edit render; create via form; duplicate name in same season → form error; delete with teams → record survives + danger notification (restrict path, `TeamResourceTest`'s precedent) |
| Admin | `TeamResourceTest.php` | Modify | Existing methods unchanged. Add `test_can_assign_a_team_to_a_division`; `test_team_persists_with_a_null_division`; `test_division_select_only_offers_divisions_from_the_selected_season` |
| Admin | `NewsResourceTest.php` | Create | list/create/edit render; create via form; duplicate slug → form error; draft (`published_at` null) saves fine |
| Admin | `GameEventsRelationManagerTest.php` | Create | RM renders; create a goal via the RM; `test_player_select_only_offers_players_from_the_two_teams_in_the_game` (`assertFormFieldExists` + options assertion, mirroring `GamesRelationManagerTest`) |
| **HTTP** | `StandingsPageTest.php` | Create | 4 spec-mapped methods above, plus design-level: `test_page_falls_back_to_the_latest_season_when_none_is_current`; `test_page_falls_back_to_a_single_season_table_when_no_divisions_exist` (D6) |
| **HTTP** | `FixturesPageTest.php` | Create | The 3 spec-mapped methods above |
| **HTTP** | `ScorersPageTest.php` | Create | 3 spec-mapped methods above, plus design-level: `test_page_shows_at_most_ten_of_each`; `test_page_renders_with_no_events_recorded` (the fresh seed produces zero events — D9) |
| **HTTP** | `NewsPageTest.php` | Create | `test_listing_shows_published_items_newest_first`; `test_listing_hides_drafts_and_future_items`; `test_detail_page_renders_a_published_item_by_slug`; `test_detail_page_returns_404_for_a_draft`; `test_detail_page_returns_404_for_an_unknown_slug` |
| Cleanup | `ExampleTest.php` (Feature) | **Delete** | D11 — would go red for an unrelated reason |

**HTTP-assertion style — first use in this codebase** (locked). The idiom for all four page tests:

```php
$this->get(route('site.standings'))
    ->assertOk()
    ->assertSee('Manaos FC')
    ->assertDontSee('Segunda');

$this->get(route('site.news.show', 'un-fichaje'))->assertNotFound();
```
`assertSee()` escapes by default, which is what we want for names with accents (`Tapajós SC`,
`Solimões FC`) — pass `escape: false` only when asserting on raw markup. Route **names** (not literal
URLs) in tests, so a future URL change does not touch 20 assertions.

Regression bar for every work unit: `php artisan test` fully green, including all Fase 2/3/4 tests,
and `npm run build` green for the token unit.

---

## Work-Unit / Branch Grouping (recommendation for `sdd-tasks`)

`delivery_strategy=auto-chain`, `chain_strategy=stacked-to-main`. **400-line budget risk: High** for
the change as a whole; every unit below is individually under it. Each unit ships its own migration
(if any) + model + admin + tests, and is independently revertible.

| # | Branch | Contents | Est. lines | Depends on |
|---|---|---|---|---|
| 1 | `fase-5/1-season-is-current` | Migration 1, `Season` fillable/casts/guard, `SeasonFactory`, `SeasonForm` Toggle, `SeasonsTable` IconColumn, seeder `is_current`, `SeasonCurrentGuardTest`, schema/seeder/resource test rows | ~230 | — |
| 2 | `fase-5/2-divisions-schema` | Migrations 2+3, `Division` model+factory, `Team` relation, `DivisionResource` (6 files), `TeamForm`/`TeamsTable`, seeder Primera/Segunda, `DivisionResourceTest`, `TeamResourceTest` additions, schema/delete/seeder rows | ~400 | 1 |
| 3 | `fase-5/3-standings-for-division` | `StandingsService::forDivision()` + `buildTable()` extraction, `StandingsServiceTest` additions | ~140 | 2 |
| 4 | `fase-5/4-news` | Migration 4, `News` model+factory, `NewsResource` (6 files), `NewsResourceTest`, schema/delete rows | ~330 | — (independent; stack after 3 for a linear chain) |
| 5 | `fase-5/5-game-events` | Migration 5, `GameEvent` model+factory, `Game`/`Player` relations, `GameEventsRelationManager`, `GameResource` registration, `GameEventsRelationManagerTest`, schema/delete rows | ~330 | — |
| 6 | `fase-5/6-goalscorers-service` | `GoalscorersService`, `ScorerRow`, `GoalscorersServiceTest` | ~200 | 5 |
| 7 | `fase-5/7-design-tokens` | `app.css` `@theme`, `vite.config.js` fonts, `ARQUITECTURA.md` + `config.yaml` Tailwind drift | ~50 | — |
| 8 | `fase-5/8-public-standings-fixtures` | `SeasonResolver` (+test), `SiteController`, `StandingsController`, `FixturesController`, layout + `standings-table`/`game-card` components, 2 views, 2 routes, `StandingsPageTest`, `FixturesPageTest`, delete `welcome.blade.php` + `ExampleTest.php` | ~400 | 3, 7 |
| 9 | `fase-5/9-public-scorers-news` | `ScorersController`, `NewsController`, `scorer-list`/`news-card` components, 3 views, 3 routes, `ScorersPageTest`, `NewsPageTest` | ~330 | 4, 6, 8 |

Rationale for the shape:
- **Schema-first, public-last.** Units 1–6 each close one domain extension end-to-end (migration →
  model → admin → tests) and are reviewable in isolation against a single locked decision. Unit 8/9
  consume everything before them, so they are last; splitting the public site in two keeps each PR
  reviewable and lets standings — the highest-value page — land first.
- **3 is separate from 2** because it is service logic reviewed under a different lens (the
  `buildTable()` extraction needs the Fase 4 regression argument in front of the reviewer, not buried
  in a 380-line schema+admin diff).
- **6 is separate from 5** for the same reason (schema/admin vs. query+fold).
- **7 is standalone and tiny** — a CSS/config-only PR that unit 8 needs and that carries zero domain
  risk; keeping it out of 8 keeps 8's diff purely PHP/Blade.
- Units **4, 5, 7** have no hard dependency on 1–3; `stacked-to-main` still chains them linearly for a
  clean diff per PR, but they can be reordered if the chain needs resequencing.

`Decision needed before apply: No` · `Chained PRs recommended: Yes` · `400-line budget risk: High`
(mitigated by the 9-unit split above; unit 8 is the closest to the ceiling and should be watched).

---

## Migration / Rollout

All five migrations are additive with working `down()`s; no data backfill, no destructive DDL, no
feature flag. `teams.division_id` stays nullable indefinitely (locked) — an existing deployment
migrates with every team unassigned and the standings page transparently uses D6's fallback until an
admin creates divisions. Rollback is per work-unit branch revert; the schema unwinds in reverse
migration order.

One ordering caveat for the deploy of unit 8: `npm run build` must run after unit 7's `vite.config.js`
change, or `@fonts` emits the stale Instrument Sans link. Standard build step, no special handling.

---

## Future-Proofing Check

Per project convention (`feedback_schema_extensibility`, established in Fase 2's design). **Target:
Fase 6 ("Pulido" — visual polish).** Nothing here forecloses it:

- **Pixel-matching the mockup** — only *tokens* land now (D10). Fase 6 changes Blade classes and can
  add the `repeating-linear-gradient` stripe pattern, radii, and spacing without touching a
  controller, service or migration. The anonymous components (`game-card`, `standings-table`, …) are
  the exact seams a polish pass wants: restyling a card is one file, not five views.
- **Recent-form chips (G/E/P)** from NOTES.md — needs the last N results per team, which `forSeason()`
  currently discards after folding (Fase 4's own carried-forward watch-item, unchanged and still
  true). Fase 6 would add a `recentForm()` sibling; `--color-win/draw/loss` are already defined for it.
- **"Partido de la semana" / live badge** — `Game` still has no status column (Fase 2 decision, not
  reopened here). A highlighted game is a query (`kickoff_at` nearest to now), not a schema change.
- **A richer news body** — `Textarea` → `RichEditor` (D7) is a form-and-view change on an already-`text`
  column; no migration.
- **"Ver todos" full leaderboard** — `?int $limit = null` (D4) is the built-in seam; a new route + view,
  no service change.
- **More event types (cards, subs)** — `type` is a plain string with PHP constants (locked); adding
  `TYPE_YELLOW_CARD` is a constant + a Select option, no migration.
- **Division ordering / promotion-relegation** — a `divisions.position` integer is additive; D6's
  `orderBy('id')` becomes `orderBy('position')` in one place.
- **News pagination** — deliberately not built (demo scope); `->get()` → `->paginate()` is a
  one-line controller change plus `->links()` in the view, and `app.css` already `@source`s the
  pagination views.
- **Filament theming** — the panel does not consume `app.css` (D10), so nothing in Fase 6's public
  restyle can leak into the admin.

**Watch-item carried forward**: the "published" rule now lives in `News::published()` **and** in the
`published_at` index's intent. Any change to the visibility semantics (e.g. a `status` enum) must
touch the scope, not the call sites — all four consumers already go through it.

---

## Open Questions

- [x] **Resolved (D1)** — exact column types and FK actions; all five verified against `state.yaml`.
- [x] **Resolved (D2)** — guard unsets others inside `saving`, no exception, no transaction.
- [x] **Resolved (D3)** — `forDivision()` is a public sibling; the shared fold is extracted as a
  private helper in the REFACTOR step, with Fase 4's suite as the pin. Duplication is the documented
  fallback if a reviewer wants `forSeason()` character-identical.
- [x] **Resolved (D5)** — `App\Http\Controllers\Site` (`Public` is a PHP reserved word); 4 controllers
  + abstract base; `SeasonResolver` in the Services layer.
- [x] **Resolved (D5)** — routes `/`, `/partidos`, `/goleadores`, `/noticias`, `/noticias/{slug}`;
  Spanish URLs, English class/route names.
- [x] **Resolved (D8)** — full field sets for `DivisionForm`, `NewsForm`, `GameEventsRelationManager`;
  the RM scopes players via `getOwnerRecord()`, not `Get`.
- [x] **Resolved (D10)** — fonts via `vite.config.js`'s existing `bunny()` mechanism + `@fonts`, not a
  new `<link>` or CSS `@import`.
- [x] **Resolved (D11)** — `welcome.blade.php` and `tests/Feature/ExampleTest.php` are deleted.
- [x] **Resolved (Spec Reconciliation #1)** — capability naming stays `public-views` (one new spec),
  superseding `proposal.md`'s two-capability sketch.
- [x] **Resolved (Spec Reconciliation #2)** — `GameEventsRelationManager` scopes via
  `getOwnerRecord()`; `admin-league-crud`'s "`Get`-based" mechanism clause should be softened to its
  own scenario's mechanism-free wording before archive.
- [ ] **OPEN — needs a spec scenario or a user veto (D6 / Spec Reconciliation #3)**: the zero-division
  standings fallback (a season with teams but no divisions renders one unnamed table via
  `forSeason()`). Not in `state.yaml`, not in `public-views`; added here because
  `teams.division_id` is permanently nullable, so the state is reachable by design. It does not block
  any other unit — only work unit 8 depends on the answer, and the fallback is a 3-line branch either
  way. Default if nobody rules: implement it as designed and let `sdd-spec` backfill one scenario.
