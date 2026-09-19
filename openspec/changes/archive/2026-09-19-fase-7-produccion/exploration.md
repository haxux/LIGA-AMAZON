# Exploration: Fase 7 — Endurecimiento para producción + seed reducido

Read-only audit of the repository at `7df6efc`, run before any planning artifact was
written. Every claim below is a file-and-line fact, not an assumption.

## 1. What already protects the app

These are real, verified controls. Fase 7 must not regress them.

| Control | Evidence |
|---|---|
| Session auth on `/admin`, bcrypt cost 12 | `AdminPanelProvider.php:31` (`->login()`), `.env.example:19` |
| `password` hashed via cast, hidden from serialization | `app/Models/User.php:14,29` |
| Login throttling, 5 attempts | `vendor/filament/filament/src/Auth/Pages/Login.php:185` |
| CSRF on every panel request | `PreventRequestForgery` — `AdminPanelProvider.php:59` |
| Encrypted cookies + session invalidation on password change | `EncryptCookies`, `AuthenticateSession` — `AdminPanelProvider.php:55,57` |
| No SQL injection surface | Only raw fragment is `DB::raw('COUNT(*)')` with no user input — `GoalscorersService.php:43` |
| No XSS surface | Single `{!! !!}` in the codebase is `nl2br(e($item->body))` — escapes first — `site/news/show.blade.php:11` |
| Mass assignment closed on all 10 models | `#[Fillable]` attribute on every model in `app/Models/` |
| Minimal public surface | 5 read-only GET routes; no API, no registration, no write endpoints — `routes/web.php` |
| Draft news never leak | Visibility is a query scope, not a view conditional — `NewsController.php:16,29` |
| Dotfiles unreachable over HTTP | `location ~ /\.(?!well-known).* { deny all; }` — `docker/nginx/default.conf:19` |
| `X-Powered-By` stripped | `docker/nginx/default.conf:17` |
| PHP-FPM runs as non-root | `USER www-data` — `docker/php/Dockerfile:18` |
| No secrets in git history | `git log --all -- .env` is empty; `.env` gitignored since the first commit |
| No seeded admin credentials | Locked by `openspec/specs/admin-panel/spec.md:22` |

## 2. Findings

### F1 — `canAccessPanel()` absent: the panel 403s for everyone in production

Filament decides panel access like this:

```php
// vendor/filament/filament/src/Http/Middleware/Authenticate.php:34-40
abort_if(
    $user instanceof FilamentUser ? (! $user->canAccessPanel($panel))
                                  : (config('app.env') !== 'local'),
    403,
);
```

`App\Models\User` does not implement `FilamentUser`. Two consequences:

- **Today (`APP_ENV=local`)**: any row in `users` reaches the panel.
- **The moment `APP_ENV=production` is set**: the `false` branch evaluates to `true` and
  **every** user, including the owner, gets a 403. The panel becomes unusable.

This is simultaneously the highest-severity security gap and a hard functional blocker.
It was deferred on purpose — `openspec/specs/admin-panel/spec.md:33` states the
restriction "MUST NOT" exist in Fase 3 and is "deferred beyond Fase 3, not scheduled to
any specific phase". Fase 7 is that phase.

### F2 — SVG uploads are accepted, and SVG is executable in the browser

`->image()` does **not** apply Laravel's `image` validation rule (which since Laravel 11
excludes SVG unless `allow_svg` is passed — `ValidatesAttributes.php:1535`). It applies a
MIME wildcard:

```php
// vendor/filament/forms/src/Components/FileUpload.php:130-137
public function image(): static { $this->acceptedFileTypes(['image/*']); ... }
// vendor/filament/forms/src/Components/BaseFileUpload.php:265
return "mimetypes:{$types}";
```

`mimetypes:image/*` matches `image/svg+xml`. Both upload fields use bare `->image()`
(`TeamForm.php:37`, `NewsForm.php:31`) and both write to `disk('public')` with
`visibility('public')`. An SVG carrying `<script>` is then served from the site's own
origin at `/storage/crests/<file>.svg` → stored XSS with full same-origin privileges,
including the admin session cookie.

Neither field sets `->maxSize()`. Filament's own source flags this exact omission:
`BaseFileUpload.php:495` — "Always use `acceptedFileTypes()` and `maxSize()`". The only
ceiling today is `client_max_body_size 20M` / `post_max_size 20M`.

### F3 — Environment is configured for development

`.env` carries `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug`, `LOG_STACK=single`,
and both DB passwords still read `change-me-in-your-local-env` (`.env.example:34-35`).
`config/app.php:29,42` default to safe values, so this is purely an environment-file
problem — but with `APP_DEBUG=true` any uncaught exception renders a stack trace plus the
full environment, DB credentials included.

There is no `.env.production.example`; `.env.example` is explicitly a dev file.

### F4 — No transport security and no security headers

Nothing in the repo terminates or enforces TLS. `SESSION_SECURE_COOKIE` is unset
(`config/session.php:172` → null → cookie sent over plain HTTP). No `Strict-Transport-Security`,
`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` or `Permissions-Policy` is
emitted anywhere — `bootstrap/app.php:14` has an empty `withMiddleware()` closure.

### F5 — No rate limiting on the public routes, and standings are uncached

All 5 public routes are unthrottled. `StandingsService::forSeason()` recomputes the whole
table per request with no cache layer. Cheap to abuse by accident (a crawler), not just
maliciously.

### F6 — nginx passes any `.php` path to FPM without `try_files`

`location ~ \.php$` (`docker/nginx/default.conf:12`) has no `try_files $uri =404` guard,
and `/storage` — the upload destination — resolves inside the docroot via the symlink.
Combined with F2 this is a second layer of the same problem; fixing F2 closes the
practical path, but the guard is one line and belongs in any production nginx config.

### F7 — `public/storage` symlink is container-absolute

`public/storage -> /var/www/html/storage/app/public`. Valid inside the container, dangling
anywhere else. Any deploy target must regenerate it with `artisan storage:link`.

### F8 — Operational gaps

`MAIL_MAILER=log` means a password-reset link would be written to the log file in
plaintext rather than sent (no reset flow is enabled today, so this is latent, not live).
`robots.txt` allows everything including `/admin`. No DB backup strategy exists. The
`/up` health endpoint (`bootstrap/app.php:12`) is public.

### F9 — Four high-severity advisories in a transitive dependency

`composer audit`, run during this exploration:

```
Found 4 security vulnerability advisories affecting 1 package.
Package: league/commonmark   Installed: 2.9.0   Fixed in: 2.10.0
  - GHSA-8rr7-cvq3-gmfh  high  DoS via distinctly-named attributes (Attributes extension)
  - GHSA-jjv6-8j6v-6j52  high  DoS in SmartPunct and Attributes extensions
  - GHSA-f8fg-pg57-v4j8  high  XSS: on* event-handler filter bypassed with U+000C form feed
  - GHSA-j8pm-gj4c-rq4x  high  DoS via crafted code fences, reference links, emphasis delimiters
```

**Reachability checked, not assumed**: `grep -rn "markdown\|Markdown" app/ resources/views/ routes/`
returns nothing. The package is a transitive dependency of `laravel/framework` (used by
`Str::markdown()` and markdown mailables), and this application calls neither. Exploitable
risk today is effectively nil; the fix is a one-package update and costs nothing.

`npm audit` could not complete: the registry rejected the request with "Invalid package
tree, run `npm install` to rebuild your package-lock.json". `package-lock.json` is out of
sync with `node_modules`. Unresolved — it needs `npm install` first, which is an apply-phase
action.

## 3. Seed-reduction impact analysis — SUPERSEDED, kept as record

> **This analysis no longer drives any task.** The owner first asked to empty the demo
> data, then to reduce it by 75%, then withdrew both: *"entonces descartemos eso, dejemos
> la bd como está y los datos default también"*. The seed, the database and all default
> data are left exactly as they are. The section is kept because it establishes two facts
> that remain useful: which tests are coupled to seed counts, and that the spec layer is
> count-agnostic — both matter to whoever changes the seed later.


Current demo seed (`DatabaseSeeder.php`): 1 season, 2 divisions, 10 teams, 10 stadiums,
180 players, 18 matchdays, 90 games = **311 domain rows**, every one of which the owner
must review, edit or delete by hand in the panel before going live.

Two constants and one roster drive all of it:

- `PLAYERS_PER_TEAM = 18` (`DatabaseSeeder.php:22`)
- `SCORED_MATCHDAYS = 10` (`DatabaseSeeder.php:24`)
- `TeamFactory::CLUBS`, 10 curated clubs (`TeamFactory.php:21-32`) — matchday and game
  counts are *derived*: `circleMethodDoubleRoundRobin()` yields `2*(N-1)` matchdays of
  `N/2` games. The docblock requires **N even**.

Assertions that hardcode seed counts — the complete list, grepped:

| File | Line | Assertion |
|---|---|---|
| `tests/Feature/DatabaseSeederTest.php` | 22-27 | 1/10/10/180/18/90 row counts |
| `tests/Feature/DatabaseSeederTest.php` | 41 | 40 unscored games, matchdays ≥ 11 |
| `tests/Feature/DatabaseSeederTest.php` | 54 | 50 scored games, matchdays ≤ 10 |
| `tests/Feature/DatabaseSeederTest.php` | 62, 76, 93-94, 101 | 90 games / 10 teams guards |
| `tests/Feature/StandingsServiceTest.php` | 311 | 10 standings rows from the demo seed |

Only those two files call `$this->seed()`. Every other test builds its own fixtures.

**The spec layer is already count-agnostic.** `league-data-model/spec.md:98` says "one
division's worth of teams"; `:175` says "all previously-seeded teams". Neither names a
number, so reducing the seed needs **no delta** against them. The single numeric mention,
`public-views/spec.md:37` ("a Primera division with 10 teams"), is a scenario fixture for
`StandingsPageTest`, which builds its own data and never seeds — unaffected.

## 4. Environment constraints discovered

- **Docker is not reachable from this WSL distro** (`docker: command not found` — Docker
  Desktop WSL integration is off). The DB cannot be re-seeded and `php artisan test`
  cannot run from here.
- **Local PHP is 8.2.29**, below `composer.json`'s `"php": "^8.3"`. Running the suite
  outside the container is not an option either.

Apply phase therefore requires the user to start Docker Desktop first. Planning, code
changes and test changes can all be written without it; only execution is blocked.

## 5. Audit against the owner's 20-point checklist

The owner supplied a generic pre-launch security checklist and asked for each item to be
verified against *this* project rather than accepted at face value. The result is recorded
here because four items were rejected as inapplicable, and "we deliberately did not do
this, for this reason" is the kind of decision that otherwise gets re-litigated later.

| # | Item | Verdict | Basis |
|---|---|---|---|
| 1 | Hide API keys | **N/A** | The project consumes no third-party API. `AWS_*` empty, `MAIL_MAILER=log`, no OAuth/payments/analytics. Only secrets are `APP_KEY` and the MySQL passwords, already outside git. |
| 2 | Purge git secrets | **Already clean** | `git log --all -p` grepped for `APP_KEY=base64:`, `sk-…`, `AKIA…`, `-----BEGIN`, literal passwords → no real hit. Only tracked candidate file is `.env.example`, which holds placeholders. **Rewriting history would change every commit hash for nothing.** |
| 3 | Use public DB key | **N/A** | A Supabase/Firebase concept — publishing an anon key because the browser talks to the database. Here the browser never touches MySQL; every query goes through PHP with server-side credentials. |
| 4 | Enable row-level security | **N/A** | Same reason, plus MySQL 8 has no RLS (a PostgreSQL/Supabase feature), plus there is no multi-tenancy. The Laravel equivalent is policies — item 7. |
| 5 | Encrypt sensitive data | **Rejected, with owner agreement** | Inventory: `users` (name, email, already-hashed password) and `players.birth_date`. Everything else is public by definition — it is a league. Owner: *"la información de todos los jugadores que colocamos son de carácter público"*. `birth_date` is unused today; `SESSION_ENCRYPT=true` covers the session side. |
| 6 | Enforce server-side auth | **Already done** | No SPA, no API; authorization resolves in PHP before render. The one hole is F1. |
| 7 | Lock record access | **Deferred — flagged high-importance** | Any panel user can edit any row. Meaningless with one operator, but the owner states a **`técnico` role is coming soon**. See §6. |
| 8 | Block field tampering | **Already done** | `#[Fillable]` on all 10 models, and — checked for the classic vector — **zero `->disabled()`, `->hidden()` or `->readOnly()` fields** across `app/Filament/`, so there is no field that reaches the request without a matching schema rule. Domain guards (`Game::booted()`, `Season::booted()`) are model-level and fire regardless of origin. |
| 9 | Secure session cookies | **Partial → in scope** | `http_only` true, `same_site` lax, `EncryptCookies` present. `SESSION_SECURE_COOKIE` unset, `SESSION_ENCRYPT` false. |
| 10 | Hash passwords | **Already done** | `#[Hidden]` + `'password' => 'hashed'` cast + `BCRYPT_ROUNDS=12` (above the default 10). |
| 11 | Rate limit login | **Already done** | Filament, 5 attempts — `Login.php:185`. |
| 12 | Add bot protection | **Rejected, with owner agreement** | The public site has **no forms at all**. The only form in the project is the panel login, already throttled, with one legitimate user. A captcha there costs the owner friction and buys nothing. The useful part of this item is public-route throttling (item 18's neighbour), which is in scope. |
| 13 | Parametrize queries | **Already done** | All Eloquent. Zero `whereRaw`/`selectRaw`/concatenation. Only raw fragment is `DB::raw('COUNT(*)')`, a constant. |
| 14 | Validate all input | **Deferred** | Stronger than expected: `shirt_number` has `minValue(1)->maxValue(99)`, scores have `minValue(0)`, `position` is a closed `Select`, uniqueness is scoped. Remaining gaps are data-integrity only, never exploitable: `founded_year` unbounded, stadium `capacity` allows negatives, `matchdays.number` has no minimum, no `maxLength` against `varchar(255)`. Owner: *"lo dejamos para después"*. See §6. |
| 15 | Escape user content | **Already done** | Blade escapes by default; the single `{!! !!}` is `nl2br(e($item->body))`. |
| 16 | Restrict file uploads | **In scope — most serious finding** | F2. |
| 17 | Trim API responses | **N/A, equivalent checked** | No API. The analogue — do the views over-expose? — checked: `birth_date` appears in no view or service, `FixturesController` eager-loads only what it renders, `StandingsService` returns `StandingRow` DTOs rather than raw models. (`->get()` without pagination on `/noticias` and `/partidos` is a performance note, not a security one.) |
| 18 | Add security headers | **In scope** | F4. |
| 19 | Force HTTPS | **In scope (code) / deferred (cert)** | F4. |
| 20 | Scan dependencies | **In scope — new finding** | F9. Not previously on the plan; surfaced by this checklist. |

## 6. Forward-looking constraints stated by the owner

Recorded here because they change what a future phase must do, and because one of them
(the `técnico` role) is the reason an access-control decision in this phase is shaped the
way it is.

- **A `técnico` (coach) role is coming soon.** The owner: *"muy pronto se implementarán el
  rol de técnico… tendrá poderes, pero serían funciones mucho más limitadas que las del
  admin, muy contadas, mayormente serían nuevas funciones más interactivas para ese
  usuario"*. Two consequences: (a) record- and resource-level authorization (item 7)
  becomes required work, not optional hardening, and it arrives on a short horizon;
  (b) the coach will **need** panel access, so a binary admin-or-nothing gate on
  `canAccessPanel()` would be the wrong shape to build now — see design D1 and D9.
- **`players.birth_date` is unused** and its purpose is undecided: *"los datos de jugadores
  no se usan aún, sería un tema para después"*. Not encrypted, not removed, not exposed.
  Whoever gives it a purpose should decide then whether it is needed at all.
- **Input-validation ranges (item 14) are deferred** to a later `fix(validation)` change.
