# Design: Fase 3 — Panel admin: Filament Resources

## Technical Approach

Five generated Filament Resources bind directly to the six Fase 2 models. No Services layer
(`config.yaml` reserves it for business logic; this is CRUD). No migrations, no model edits.
Two integrity rules that Fase 2 pushed to the DB/model layer (`home_team_id <> away_team_id`,
four composite uniques) get **mirrored** as Filament form rules — verification below proves they
otherwise fail *silently or as a 500*, never as a field error. FK delete behavior is left exactly
as Fase 2 designed it; only the *presentation* of the restrict failure is softened.

## Live Verification (performed against `vendor/`, not docs)

Read-only inspection of the installed package — no probe files generated, nothing to clean up.

| # | Question | Verified answer | Evidence |
|---|---|---|---|
| V1 | Installed version | `filament/filament` **v5.7.6** | `composer.lock:1042-1043` |
| V2 | Stub layout: inline vs separate | **Separate files**, always, unless `--simple` / `--embed-*` / a `FileGenerationFlag` is set | `MakeResourceCommand::createFormSchema()/createTable()/hasEmbeddedSchemas()/hasEmbeddedTable()` |
| V3 | Are flags set in this project? | **No** — no `config/filament.php`, no `FileGenerationFlag` reference in `app/` | glob + grep |
| V4 | Resource lives in a subdirectory? | **Yes** — `{Resources}/{PluralModel}/{Model}Resource.php` (`PANEL_RESOURCE_CLASSES_OUTSIDE_DIRECTORIES` unset) | `configureLocation()` L481-503 |
| V5 | Exact schema/table filenames | `Schemas/{Model}Form.php` (**singular**), `Tables/{PluralModel}Table.php` (**plural**) | L553, L606 |
| V6 | Does the `Game` guard surface inline? | **No — worse: it disappears.** Livewire's `SupportValidation::exception()` catches *any* `ValidationException`, sets the error bag and calls `stopPropagation()` (no 500). But the key is `away_team_id` while the field's statePath is `data.away_team_id`, and the field wrapper renders only `$errors->has($statePath)`. Net effect: save aborts with **zero user feedback**. | `livewire/.../SupportValidation.php:71-76`; `CreateRecord.php:323` `->statePath('data')`; `forms/.../field-wrapper.blade.php:53` |
| V7 | Is `->different()` sufficient? | **No.** `fieldComparisonRule()` resolves the sibling path correctly, but Laravel's `validateDifferent` compares with `===` (`ValidatesAttributes.php:736`). Hydrated `int 3` vs posted `'3'` slips through — the exact case Fase 2's `(int)` cast in `Game::booted()` was written for. |
| V8 | Do empty scores save as `null` or `0`? | **`null`.** `HasState::getStateToDehydrate()` L294-298 converts `''` → `null` before casts. No `dehydrateStateUsing` needed. |
| V9 | Does a `RelationManager` support `HasOne`? | **Yes.** `getRelationship(): Relation\|Builder` is type-agnostic and `CreateAction` falls through to `$relationship->save($record)`, which `HasOne` inherits from `HasOneOrMany`. | `InteractsWithRelationshipTable.php:58-61`; `CreateAction.php:87-103` |
| V10 | Does `DeleteAction` catch DB errors? | **No try/catch at all** — `->action(fn (Model $record) => $record->delete())`. A `restrictOnDelete` `QueryException` escapes to Livewire's error handler. | `DeleteAction.php:51-61` |

## Architecture Decisions

### D1 — File layout: generator default (separate `Schemas/` + `Tables/`)

**Choice**: accept the v5.7.6 default — per-model subdirectory, separate form/table classes.
**Rejected**: `--simple` for the light models (Season) — saves 4 files but splits the codebase into
two idioms, and Team/Matchday *cannot* be simple (relation managers render on Edit/View pages, which
`--simple` replaces with a single Manage page). `--embed-schemas`/`--embed-table` — fights the
installed convention for no gain.
**Rejected**: `--view` pages — adds a 7th file plus an Infolist per resource for a demo with no
read-only audience. Edit page is the view.

### D2 — Game home/away guard: Filament closure rule on `away_team_id` (**the definitive answer**)

Per **V6**, doing nothing means a silent no-op save. Per **V7**, `->different('home_team_id')` is
type-unsafe. `CanBeValidated::getValidationRules()` evaluates a `Closure` rule with Filament utility
injection and uses its *return value* as the rule (L872), so:

```php
Select::make('home_team_id')->relationship('homeTeam', 'name')->required()->live()

Select::make('away_team_id')
    ->relationship('awayTeam', 'name')
    ->required()
    ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
        if (filled($value) && (int) $value === (int) $get('home_team_id')) {
            $fail('A team cannot play against itself.');
        }
    })
```

**Why this and not the alternatives**:

| Option | Verdict |
|---|---|
| Rely on `Game::booted()` alone | **Broken** — silent failure (V6) |
| `->different('home_team_id')` | Type-unsafe `===` (V7); would let `3` vs `'3'` reach the model guard → back to V6 |
| `try/catch` in `handleRecordCreation`/`handleRecordUpdate` + `Notification::danger()` | Works, but must be duplicated on 2 pages **and** the `GamesRelationManager` modal (4 sites), and yields a toast, not a field error. Spec requires "field-level form error". |
| **Closure `rule()` on the field** | One line, one place, `(int)`-safe (mirrors `Game.php:40`), `$get()` resolves relatively so the **same `GameForm` works unchanged inside the relation manager modal**, error lands on `data.away_team_id` → renders inline. **Chosen.** |

`->live()` on `home_team_id` is load-bearing: it keeps the server-side state fresh for `$get()`.
The model guard stays as the last line of defense for tinker/seeder writes — the Filament rule is a
mirror, not a replacement. Spec scenario "no exception page or 500" is satisfied because the rule
fails *before* the model save is attempted.

### D3 — Mirror the four composite uniques as form rules

Same failure class as D2, unaddressed by the spec: `seasons.name`, `teams(season_id,name)`,
`players(team_id,shirt_number)`, `matchdays(season_id,number)` are DB-only. Without a form rule,
a duplicate entry raises a raw `QueryException` (Livewire error screen), not a field error.

```php
TextInput::make('shirt_number')->numeric()->required()
    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('team_id', $get('team_id')))
```

Same shape for the other three. **Rejected**: leaving them raw — the Fase 3 success criterion "no
500, no stack trace" is written about Game but the operator experience is identical here.

### D4 — Delete UX: no pre-check guard, one `try/catch` translation

The spec is explicit: *"no additional application-level guard intercepts it before the database"*.
So **no** `->before()` existence check. Per **V10** the `QueryException` would otherwise reach
Livewire raw. Reconciliation: let the DB reject, then translate.

On `TeamResource`'s `DeleteAction` (both the table row action and the Edit-page header action):

```php
DeleteAction::make()->action(function (Team $record, DeleteAction $action): void {
    try { $record->delete(); }
    catch (QueryException) {
        Notification::make()->danger()
            ->title('Team cannot be deleted')
            ->body('This team still has games. Delete or reassign them first.')
            ->send();
        $action->halt();
    }
    $action->success();
});
```

This is post-DB, so the spec's "database-level restriction surfaces as-is" holds — the DB still
does the rejecting. Everything else (Season, Player, Stadium, Matchday, Game) cascades, so
Filament's stock `requiresConfirmation()` modal (`DeleteAction.php:37`) is sufficient — **no change**.

### D5 — Navigation grouping

`protected static string|UnitEnum|null $navigationGroup` per Resource
(`HasNavigation.php:24`) + `->navigationGroups(['League', 'Competition'])` on the panel for
deterministic ordering. Group `League`: Season, Team, Player. Group `Competition`: Matchday, Game.
This is the *only* edit to `AdminPanelProvider.php`.

## File Inventory (exact, derived from V4/V5)

Per Resource (6 files): `{Plural}/{Model}Resource.php`, `{Plural}/Schemas/{Model}Form.php`,
`{Plural}/Tables/{Plural}Table.php`, `{Plural}/Pages/List{Plural}.php`, `Create{Model}.php`, `Edit{Model}.php`.

| Path under `app/Filament/Resources/` | Count |
|---|---|
| `Seasons/` — `SeasonResource.php`, `Schemas/SeasonForm.php`, `Tables/SeasonsTable.php`, `Pages/{ListSeasons,CreateSeason,EditSeason}.php` | 6 |
| `Teams/` — same shape (`TeamResource`, `TeamForm`, `TeamsTable`, 3 pages) | 6 |
| `Teams/RelationManagers/StadiumRelationManager.php` (relationship `stadium` → studly `Stadium`) | 1 |
| `Teams/RelationManagers/PlayersRelationManager.php` (relationship `players` → **plural** name) | 1 |
| `Players/` — `PlayerResource`, `PlayerForm`, `PlayersTable`, 3 pages | 6 |
| `Matchdays/` — `MatchdayResource`, `MatchdayForm`, `MatchdaysTable`, 3 pages | 6 |
| `Matchdays/RelationManagers/GamesRelationManager.php` (relationship `games` → **plural**) | 1 |
| `Games/` — `GameResource`, `GameForm`, `GamesTable`, 3 pages | 6 |
| **Total new PHP files** | **33** |

Note the naming correction vs. the proposal: the generator derives the relation-manager basename
from the *relationship method*, so it is `PlayersRelationManager` / `GamesRelationManager`
(plural), not `PlayerRelationManager` / `GameRelationManager`. `StadiumRelationManager` is singular
because the relationship is `stadium()`.

Also modified: `AdminPanelProvider.php` (D5), plus `getRelations()` on `TeamResource` and
`MatchdayResource` (the generator prints the reminder but does not wire it).

## Form Field Mapping

| Model | Fields |
|---|---|
| **Season** | `TextInput name` required, `->unique(ignoreRecord: true)`; `DatePicker start_date`, `DatePicker end_date` |
| **Team** | `Select season_id` `->relationship('season','name')` required searchable preload; `TextInput name` required + scoped unique (D3); `TextInput short_name` required; `FileUpload crest_path` `->image()->disk('public')->directory('crests')->visibility('public')`; `TextInput founded_year` `->numeric()` (unsignedSmallInteger) |
| **Player** | `Select team_id` `->relationship('team','name')` required searchable preload; `TextInput name` required; `Select position` `->options(['Goalkeeper','Defender','Midfielder','Forward'])->required()->native(false)` (plain string column — no enum cast, per exploration); `DatePicker birth_date` nullable; `TextInput shirt_number` numeric required `->minValue(1)->maxValue(99)` + team-scoped unique (D3) |
| **Stadium** (RM only) | `TextInput name` required; `TextInput city` required; `TextInput capacity` `->numeric()` nullable. `team_id` is set by the relationship, **never a field** |
| **Matchday** | `Select season_id` relationship required; `TextInput number` numeric required + season-scoped unique (D3); `DatePicker date` nullable with helper text "nominal label — `Game.kickoff_at` is authoritative" |
| **Game** | `Select matchday_id` `->relationship('matchday','number')` + `->getOptionLabelFromRecordUsing(fn (Matchday $r) => "{$r->season->name} · MD {$r->number}")` (bare `number` is ambiguous across seasons); `Select home_team_id` (D2, `->live()`); `Select away_team_id` (D2, closure rule); `DateTimePicker kickoff_at` nullable; `TextInput home_score`/`away_score` `->numeric()->minValue(0)` **not required, no `->default(0)`** — V8 proves `''` dehydrates to `null` |

## Tables, Filters, Relation Managers

| Table | Columns | Sort / Search / Filter |
|---|---|---|
| `SeasonsTable` | name, start_date, end_date, `teams_count` | name searchable+sortable; dates sortable |
| `TeamsTable` | `ImageColumn crest_path` `->disk('public')->circular()`, name, short_name, season.name, `players_count` | name/short_name searchable; `SelectFilter season` |
| `PlayersTable` | name, team.name, position `TextColumn->badge()`, shirt_number | name searchable; team.name searchable+sortable; **`SelectFilter position`** with the 4 values (spec requirement); `SelectFilter team` |
| `MatchdaysTable` | number, season.name, date, `games_count` | number sortable (default asc); `SelectFilter season` |
| `GamesTable` | matchday.number, homeTeam.name, home_score, away_score, awayTeam.name, kickoff_at | team names searchable; `SelectFilter matchday`, `SelectFilter` season via `->relationship('matchday.season','name')`; **default sort `matchday_id asc`**; `->defaultPaginationPageOption(25)` |

**Relation managers**

| RM | Relation | Wiring |
|---|---|---|
| `StadiumRelationManager` | `HasOne` (V9) | `$relationship = 'stadium'`; table lists 0-or-1 row; header `CreateAction` **hidden once a record exists** (`->visible(fn ($livewire) => $livewire->getOwnerRecord()->stadium()->doesntExist())`) — mirrors the unique `stadiums.team_id` constraint from the UI side instead of letting the insert 500 |
| `PlayersRelationManager` | `HasMany` | Reuses `PlayerForm` **minus** the `team_id` Select (set by the relationship); position filter available here too |
| `GamesRelationManager` | `HasMany` | Reuses `GameForm` **minus** the `matchday_id` Select. The home/away Selects and the D2 closure rule come along unchanged — `$get()` resolves relative to the modal's container, so no duplication |

Extract the shared parts as static methods on `PlayerForm` / `GameForm` (e.g.
`GameForm::teamAndScoreFields()`) so the RM and the Resource share one definition.

## Testing Strategy (`strict_tdd: true`)

| # | Layer | RED assertion |
|---|---|---|
| 1 | Feature | Each of the 5 List/Create/Edit pages renders 200 (`livewire(ListTeams::class)->assertOk()`) |
| 2 | Feature | Create + Edit round-trip per Resource via `->fillForm()->call('create')->assertHasNoFormErrors()` |
| 3 | Feature | **D2**: fill Game form with equal home/away → `assertHasFormErrors(['away_team_id'])`; assert `Game::count()` unchanged. Repeat with `home_team_id` as `int` and `away_team_id` as `string` to pin V7 |
| 4 | Feature | Empty score inputs persist `null`, not `0` (pins V8) |
| 5 | Feature | `Storage::fake('public')` + `UploadedFile::fake()->image()` → `crest_path` starts with `crests/`, file exists on the fake disk |
| 6 | Feature | `PlayersTable` `SelectFilter position` returns only matching records |
| 7 | Feature | `PlayersRelationManager` on team A lists A's players only |
| 8 | Feature | **D3**: duplicate `shirt_number` within a team → `assertHasFormErrors(['shirt_number'])`, no `QueryException` |
| 9 | Feature | **D4**: delete a Team with games → danger notification, team still exists, no unhandled exception |
| 10 | Regression | Full Fase 2 suite still green |

## Migration / Rollout

No migration, no schema, no data change — purely additive files plus one nav edit. Rollback per
`proposal.md`. Branch base is **`fase-2/5-storage-and-verify`**, not `master` (Fase 2's 5-branch
stack is unmerged; `master` has no models).

## Work-Unit Recommendation for `sdd-tasks` (advisory — `sdd-tasks` decides)

33 new files ≈ 1500-2000 lines. **400-line budget risk: High.** `delivery_strategy=auto-chain`,
`chain_strategy=stacked-to-main` → 4 stacked slices off `fase-2/5-storage-and-verify`:

| Slice | Branch | Contents | Est. |
|---|---|---|---|
| 1 | `fase-3/1-season-matchday` | `SeasonResource` + `MatchdayResource` (12 files, no RM yet) + D3 uniques + D5 nav | ~350 |
| 2 | `fase-3/2-team-stadium` | `TeamResource` + crest `FileUpload` + `StadiumRelationManager` + D4 delete | ~350 |
| 3 | `fase-3/3-player` | `PlayerResource` + `PlayersRelationManager` + position filter | ~300 |
| 4 | `fase-3/4-game` | `GameResource` + `GamesRelationManager` + **D2** + shared-form extraction | ~400 |

Ordering is dependency-driven: Team needs Season; Player needs Team; Game needs Matchday + Team.
Each slice ends with its own passing tests and a working panel — an autonomous, revertable unit.

## Future-Proofing Check

Per project convention (`feedback_schema_extensibility`, established in Fase 2's design).

- **Fase 4 (`StandingsService`)** — not foreclosed. Standings derive from `games` with non-null
  scores; nothing here writes derived state, caches aggregates, or adds a status column. The
  admin panel will simply become the input surface. A future `StandingsWidget` drops into
  `app/Filament/Widgets/` (already discovered by the panel) with zero Resource changes.
- **Fase 5 (public Blade views)** — not foreclosed. Zero logic lives in the Resources: field
  definitions and column definitions only. `crest_path` is stored as a plain relative path on the
  `public` disk, so a Blade view renders it with `Storage::url()` / `asset('storage/...')` without
  knowing Filament exists.
- **Divisions / player match stats / News** (deferred by Fase 2) — additive. A `teams.division`
  column becomes one more field in `TeamForm` + one `SelectFilter`; a `game_player_stats` table
  becomes a new RelationManager on `GameResource`. Neither restructures anything designed here.
- **Auth/roles (deferred again by this Fase)** — `canAccessPanel()` and per-Resource
  `can*()` policies are additive hooks on `Resource`; adding `filament-shield` later regenerates
  policies without touching form/table classes.

**Watch-item carried forward**: D2 and D3 create a *deliberate duplication* — the same invariants
now live in the DB, in `Game::booted()`, and in Filament form rules. This is intentional (V6 proves
the model layer cannot surface itself in the UI), but if a `Game` write path is ever added outside
Filament (CSV importer, API sync), the form rules will not travel with it — only the model guard
will. Flagged so it is a re-evaluation, not a rediscovered bug.

## Open Questions

- [x] **Resolved (V6/V7/D2)**: the model `ValidationException` does *not* surface — a Filament-side
  closure `rule()` on `away_team_id` is required, and `->different()` alone is insufficient.
- [x] **Resolved (V2-V5/D1)**: 5.7.6 emits separate `Schemas/{Model}Form.php` +
  `Tables/{Plural}Table.php` inside a `{Plural}/` subdirectory.
- [x] **Resolved (proposal Q3)**: `GameResource` is **not** default-scoped to a season. It gets
  `SelectFilter` on matchday and season plus a default `matchday_id` sort. Hard scoping would break
  the spec's "cross-matchday fixture lookup" scenario; at 90 rows/season a filter is enough.
