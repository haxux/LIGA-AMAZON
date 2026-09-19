# Design: Fase 7 — Endurecimiento para producción + seed reducido

## Technical Approach

Two constraints shape every decision below.

**The hosting target is undecided.** Anything whose correct value depends on where this
runs (TLS termination, proxy headers, nginx directives, opcache flags) is either made
*opt-in through config* — so the repo ships a safe default and the deploy flips one
switch — or deferred to `DESPLIEGUE.md`. Nothing is hardcoded to a host that may not be
chosen.

**Nothing can be executed here.** Docker is unreachable from this WSL distro and local PHP
is 8.2.29 against a `^8.3` requirement (`exploration.md` §4). Every design decision is
therefore justified from source that was actually read — Filament's and Laravel's vendor
code is cited by file and line throughout — rather than from a trial run. The apply phase
does not begin until the suite can be run.

The change is additive: one new model interface implementation, one new middleware, one
new config key, two form field constraints, three new test files, two documents, and a
seed that produces fewer rows. No migration, no schema change, no new domain concept.

## Architecture Decisions

### D1 — `canAccessPanel()` returns `true`, guarded by a no-registration regression test

Filament's access check has exactly two branches (`Authenticate.php:34-40`, quoted in
`exploration.md` §F1). Implementing the interface moves the app off the environment-
dependent branch permanently — that alone is what unblocks production. The remaining
question is only *what the method returns*.

| Option | Tradeoff |
|---|---|
| **`return true` (chosen)** | Honest: describes the real invariant — every row in `users` was created deliberately at the CLI. Zero new surface. Weak *only* if a registration path is ever added. |
| `is_admin` boolean column | Stricter, but adds a migration, a second user-creation path, and a way to lock yourself out of your own panel. Overturns "no seeded credentials" indirectly by needing a grant mechanism. |
| `email` allow-list in config | Ties authentication to an env var; rotating the owner's email becomes a deploy. |
| Spatie roles/permissions | A dependency and a permissions model for a single operator. Disproportionate. |

The weakness of `return true` is conditional, not inherent: it becomes a hole the moment
user creation stops being CLI-only. So the guard is written as a **test on that
precondition**, not as a comment:

```php
// tests/Feature/PanelAccessTest.php
public function test_no_public_registration_path_exists(): void
{
    // canAccessPanel() returning true is only safe while `users` can be
    // populated exclusively by `make:filament-user`. If either guard below
    // ever fails, canAccessPanel() must become a real check first.
    $this->assertFalse(Filament::getPanel('admin')->hasRegistration());
    $this->assertNull(Route::getRoutes()->getByName('register'));
}
```

That test fails loudly the day someone enables registration, which is precisely the day
the policy needs revisiting. The owner confirmed this option, and the coming `técnico` role
makes it the right shape rather than merely the cheap one — see D9.

**Consequence for the spec layer**: `admin-panel/spec.md`'s "Panel Access Policy (Demo
Scope)" requirement says the system "MUST NOT implement `canAccessPanel()` role
restrictions" and that the deferral is "not scheduled to any specific phase". Fase 7 is
that phase, so the requirement is **replaced**, not extended — see the delta.

### D2 — Explicit MIME allow-list replaces `->image()`, not supplements it

`->image()` is not additive. It *sets* `acceptedFileTypes(['image/*'])`
(`FileUpload.php:130-137`), which becomes the validation rule `mimetypes:image/*`
(`BaseFileUpload.php:265`). Calling `acceptedFileTypes()` afterwards overwrites that
wildcard, which is exactly what is wanted:

```php
FileUpload::make('crest_path')
    ->image()                                   // keeps the image preview/editor UI
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
    ->maxSize(2048)                             // KB
    ->disk('public')
    ->directory('crests')
    ->visibility('public'),
```

`->image()` is kept *ahead* of the allow-list because it also switches the component to
image-preview mode; only its MIME side is overridden. Order matters and is load-bearing —
reversing the two lines restores the wildcard.

`image/gif` is included at the owner's request. It is safe in a way SVG is not: GIF is a
raster container with no scripting facility, and `X-Content-Type-Options: nosniff` (D3)
closes the historical polyglot angle where a browser could be coaxed into re-interpreting
the bytes as something executable. The exclusion that carries the security weight is SVG.

Why an allow-list rather than a deny-list for SVG: `mimetypes:` compares against the
file's detected MIME, and new script-bearing image formats are a moving target. Naming
the four formats a crest can plausibly be is stable; enumerating everything dangerous is
not.

`maxSize(2048)` (2 MB) is below nginx's `client_max_body_size 20M` and PHP's
`post_max_size 20M`, so rejection happens in Laravel with a readable validation message
rather than as a 413 from the proxy. Filament's own source asks for exactly this pairing
(`BaseFileUpload.php:495`).

Rejected: validating on file extension. The upload is stored under a Filament-generated
name; the extension is attacker-influenced and the MIME check is the real gate.

### D3 — Security headers as Laravel middleware, appended globally

| Option | Tradeoff |
|---|---|
| nginx `add_header` | Correct for this stack — but `docker/nginx/default.conf` is the *dev* config and the production web server may not be nginx at all. Ships nothing portable. |
| **Global middleware (chosen)** | Travels with the app to any host. Covers `/admin` as well as the public site. Testable in the suite. |
| Per-route middleware | Would have to be attached in two places (web routes and the Filament panel stack) and would drift. |

Registration uses `append()`, the *global* stack, not `web(append:)`. Filament's panel
routes do not run through the `web` group — they carry their own list
(`AdminPanelProvider.php:54-63`) — so a `web`-scoped registration would leave the admin
panel bare, which is the surface that matters most.

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(SecurityHeaders::class);
})
```

Headers and why each one:

| Header | Value | Reason |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | Stops a browser re-interpreting an uploaded file as HTML/JS regardless of its served type. Second line of defence behind D2. |
| `X-Frame-Options` | `SAMEORIGIN` | Clickjacking on the admin panel. `SAMEORIGIN`, not `DENY` — Filament is same-origin throughout and `DENY` risks breaking a future preview pane. |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Keeps `/admin/...` paths out of third-party referrer logs. |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), interest-cohort=()` | The app uses none of these; denying them costs nothing. |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | **Conditional on `$request->secure()`.** |

The HSTS condition is the one genuinely dangerous header here. Emitting it over plain
HTTP, or before a certificate exists, pins every visitor's browser to HTTPS for a year
against a site that may not serve it — a self-inflicted outage that cannot be recalled by
removing the header. Gating on `$request->secure()` makes it physically impossible to send
before TLS is live. `preload` is deliberately omitted: it is irreversible on a timescale
measured in browser release cycles.

No `Content-Security-Policy`. Filament and Livewire emit inline `<script>` and `<style>`;
a real policy requires nonce propagation through Filament's asset pipeline, and a wrong
one silently breaks the panel. Out of scope with rationale, recorded in `DESPLIEGUE.md`.

### D4 — HTTPS enforcement is opt-in config, not inferred from `APP_ENV`

```php
// config/app.php
'force_https' => (bool) env('APP_FORCE_HTTPS', false),

// AppServiceProvider::boot()
if (config('app.force_https')) {
    URL::forceScheme('https');
}
```

| Option | Tradeoff |
|---|---|
| `if (app()->isProduction())` | Couples two unrelated switches. Makes it impossible to run a production-like environment locally to reproduce a bug, and causes an infinite redirect loop behind any proxy that terminates TLS without a trusted `X-Forwarded-Proto`. |
| **Explicit `APP_FORCE_HTTPS` (chosen)** | One line in the production env file. Default `false`, so local dev and the test suite are untouched. Reversible in seconds if a deploy misbehaves. |
| Nothing; rely on the proxy | Works until one absolute URL (a password-reset link, an asset) is generated as `http://`. |

`URL::forceScheme()` fixes *generated* URLs. It is not a redirect and does not by itself
trust a proxy's forwarded headers — `->trustProxies()` in `bootstrap/app.php` is the
matching piece, and its correct value (the proxy's IP, or `'*'`) is knowable only once the
host is chosen. Deferred to `DESPLIEGUE.md`, and called out there explicitly so the pair
is not half-applied.

`SESSION_SECURE_COOKIE=true` belongs to the same switch and lives in
`.env.production.example`; `config/session.php:172` already reads it.

### D5 — Throttle the public route group, leave the panel to Filament

```php
Route::middleware('throttle:60,1')->group(function (): void {
    // the five public GET routes
});
```

60 requests/minute/IP on read-only pages: generous for a human, a real ceiling on a
crawler hammering the uncached standings computation (F5). The panel is deliberately
excluded — Filament already throttles the one endpoint that matters, login, at 5 attempts
(`Login.php:185`), and a blanket throttle on an admin doing bulk data entry would fire on
legitimate use.

`/up` is registered in `bootstrap/app.php`'s `health:` option, outside `routes/web.php`,
and is left unthrottled so an uptime monitor is never rate-limited.

This does not replace caching the standings. It bounds the damage; the fix is a separate
change (noted out of scope).

### D6 — WITHDRAWN: seed reduction

> **Withdrawn before apply.** The owner asked first to empty the demo data, then to reduce
> it by 75%, then withdrew both: *"entonces descartemos eso, dejemos la bd como está y los
> datos default también"*. No seeder, factory, test or database row is touched by this
> change. The decision is kept rather than deleted for two reasons: the numbering of D7 and
> D8 is referenced from `tasks.md` and `state.yaml`, and the analysis below is the record
> whoever *does* change the seed later will need.

<details>
<summary>Original decision, superseded</summary>

#### Reduce the seed by slicing the roster, not by shrinking `TeamFactory::CLUBS`

```php
private const TEAMS = 6;
private const PLAYERS_PER_TEAM = 5;   // was 18
private const SCORED_MATCHDAYS = 5;   // was 10

$teams = collect(TeamFactory::CLUBS)
    ->take(self::TEAMS)
    ->map(fn (array $club, string $name) => Team::factory()->create([...]))
    ->values();
```

`CLUBS` stays at ten entries. It is not only the seeder's roster — `TeamFactory::definition()`
draws from it with `fake()->unique()->randomElement(array_keys(self::CLUBS))`
(`TeamFactory.php:41`). Cutting the constant to six would shrink that unique pool to six,
and any test creating a seventh team through the factory would throw Faker's
`OverflowException`. The seeder takes a slice; the pool is untouched.

Resulting shape — matchdays and games are *derived*, never set:

| Entity | Before | After | Source |
|---|---|---|---|
| Season | 1 | 1 | unchanged |
| Divisions | 2 | 2 | unchanged — Primera populated, Segunda empty |
| Teams | 10 | 6 | `TEAMS` |
| Stadiums | 10 | 6 | one per team |
| Players | 180 | 30 | `TEAMS × PLAYERS_PER_TEAM` |
| Matchdays | 18 | 10 | `2 × (TEAMS − 1)` |
| Games | 90 | 30 | `matchdays × TEAMS/2` |
| **Total domain rows** | **311** | **85** | **−72.7%** |

Six is the floor, not a preference. `circleMethodDoubleRoundRobin()` documents "for N
teams (N even)" (`DatabaseSeeder.php:96`) and pairs `$current[$i]` with
`$current[$teamCount - 1 - $i]`; an odd count needs a bye round the method does not
implement. Four teams would satisfy the arithmetic but yield a two-game matchday and a
four-row table, which stops resembling a league.

`SCORED_MATCHDAYS = 5` of 10 preserves the property the seed exists to demonstrate — a
season in progress, half played, half pending — which `league-data-model/spec.md:98`
requires and which would be lost by scoring all or none.

</details>

### D7 — Panel-access test drives the real middleware, not the method

Asserting `$user->canAccessPanel($panel) === true` would pass against a method that is
never consulted. The regression being guarded is the *403 in production*, so the test
reproduces the production branch:

```php
public function test_authenticated_user_reaches_the_panel_in_production(): void
{
    config()->set('app.env', 'production');

    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertSuccessful();   // 403 before this change
}
```

`config()->set()` rather than a `.env` change because `Authenticate.php:38` reads
`config('app.env')` directly. The existing suite's Filament tests use
`Livewire::test(...)` (`TeamResourceTest.php:33`), which bypasses HTTP middleware
entirely — which is exactly why this gap survived 169 passing tests, and why this one test
must go through the HTTP kernel instead.

### D8 — Upload test asserts rejection through the form, not the validator

```php
Livewire::test(CreateTeam::class)
    ->fillForm([..., 'crest_path' => UploadedFile::fake()->create('evil.svg', 8, 'image/svg+xml')])
    ->call('create')
    ->assertHasFormErrors(['crest_path']);
```

`UploadedFile::fake()->create(name, kb, mimeType)` — not `->image('evil.svg')`, which
would produce a real raster image with a misleading name and prove nothing about MIME
handling. This mirrors the existing happy-path upload test (`TeamResourceTest.php:124-142`)
so both live in the same idiom, and a companion assertion confirms a legitimate PNG still
passes — a rule that rejects everything is not a fix.

### D9 — `canAccessPanel()` is not where the coming `técnico` role gets restricted

The owner states a coach role arrives soon, with *"funciones mucho más limitadas que las
del admin, muy contadas, mayormente… nuevas funciones más interactivas para ese usuario"*.
That is new information after D1 was written, and it is worth being explicit that it
**confirms** D1 rather than undermining it.

`canAccessPanel()` is a boolean door: in or out. A coach who can use "more interactive
functions" in the panel is, by definition, *in*. So whatever gate is written there would
have to return `true` for them anyway, and the real restriction — which resources they
see, which records they may touch, which actions are available — lives one layer down:

| Layer | Question it answers | Who owns it |
|---|---|---|
| `canAccessPanel()` | May this user open the panel at all? | This phase — `true` |
| Panel / resource visibility | Which resources appear in their navigation? | Next phase |
| Policies (`viewAny`, `update`, `delete`…) | May they act on *this* record? | Next phase |

Had D1 chosen an `is_admin` boolean instead, the coach's arrival would mean granting them
`is_admin` — the column would then mean "can log in", not "is an administrator", and the
name would be a lie within one phase. Worse, it would create the impression that access
control exists when the resource layer is still wide open.

So the forward-compatible move is to keep the door open and honest, and to make the
*precondition* explicit and testable (D1's no-registration test), so the day user creation
stops being CLI-only the suite says so. `exploration.md` §6 records the coach role as a
near-term constraint; `DESPLIEGUE.md` records policies as the follow-up with its trigger
condition, rather than as a vague "someday".

What this phase must **not** do is pre-build a role system for a specification that does
not exist yet. The coach's permissions are described as "muy contadas" and "en el futuro";
designing a permission model around that sentence would be guessing.

## Testing Strategy

Every security change lands RED first. The three new tests must be demonstrated failing
against the current code before the fix is written, because each one targets a hole that
169 green tests did not notice:

| Test | Fails today because |
|---|---|
| `PanelAccessTest::test_authenticated_user_reaches_the_panel_in_production` | 403 — `User` is not a `FilamentUser` |
| `UploadValidationTest::test_svg_crest_is_rejected` | `mimetypes:image/*` accepts `image/svg+xml` |
| `SecurityHeadersTest::test_public_response_carries_hardening_headers` | no middleware emits them |

**No existing test is adjusted.** With the seed reduction withdrawn, nothing this change
touches is coupled to seed row counts, so all 169 existing tests must pass unmodified —
which makes them a clean regression signal rather than a moving target.

The suite is the gate for the whole change. **It has not been run and cannot be run from
this environment** — that is a precondition on apply, not an outcome to be assumed.

## Work-Unit Recommendation

Five units, severity-ordered, each independently revertible. Units 1 and 2 are the
blockers and should land and be verified before the rest is written.

| Unit | Goal | Est. lines |
|---|---|---|
| 1 | `canAccessPanel()` + `PanelAccessTest` | ~50 |
| 2 | Upload allow-list + `maxSize` + `UploadValidationTest` | ~70 |
| 3 | `SecurityHeaders` middleware, `force_https`, throttle, robots.txt + `SecurityHeadersTest` | ~120 |
| 4 | `.env.production.example`, `DESPLIEGUE.md`, `ARQUITECTURA.md` roadmap | ~180 (docs) |
| 5 | `league/commonmark` update, `package-lock.json` rebuild, audit results recorded | ~10 (lockfiles aside) |

Total well under the 400-line review budget excluding unit 4, which is documentation and
reviewable at a glance, and unit 5, which is lockfile churn. Single branch
`fase-7/1-produccion`, five commits.
