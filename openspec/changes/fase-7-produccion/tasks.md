# Tasks: Fase 7 — Endurecimiento para producción + seed reducido

**Branch**: `fase-7/1-produccion` (single branch, five commits — per design's Work-Unit
Recommendation)

## ⛔ Precondition — apply is blocked until this passes

- [ ] 0.1 Enable Docker Desktop's WSL integration for this distro, then confirm
      `docker compose ps` responds. **Verified blocked as of planning**: `docker` is not on
      PATH in this WSL distro, and local PHP is 8.2.29 against `composer.json`'s `^8.3`, so
      the suite cannot run outside the container either (`exploration.md` §4).
- [ ] 0.2 `docker compose up -d` and confirm `docker compose exec app php artisan test`
      reports the current baseline **169/169 green** before any change is made. A
      pre-existing failure must be known now, not discovered mid-apply.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~300 code/tests + ~180 docs |
| 400-line budget risk | Low (code), docs reviewable at a glance |
| Chained PRs recommended | No |
| Suggested split | Single PR, five commits; units 1 and 2 are independently revertible |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: **No** — all six open questions in `proposal.md` are resolved
with the owner.

### Suggested Work Units

| Unit | Goal | Commit | Notes |
|------|------|--------|-------|
| 1 | `canAccessPanel()` | `fix(security)` | Blocker. Land and verify before anything else. |
| 2 | Upload allow-list + max size | `fix(security)` | Blocker. Independent of unit 1. |
| 3 | Headers, HTTPS switch, throttle, robots | `feat(security)` | Depends on nothing; largest code unit. |
| 4 | `.env.production.example`, `DESPLIEGUE.md`, roadmap | `docs` | Pure documentation. |
| 5 | Dependency advisories cleared | `chore(deps)` | `league/commonmark` ≥ 2.10.0 + lockfile rebuild. |

## Phase 1: Panel access — the production blocker (TDD)

- [ ] 1.1 RED — Create `tests/Feature/PanelAccessTest.php` with three tests:
      (a) `config()->set('app.env', 'production')` then `actingAs(user)->get('/admin')`
      asserts successful; (b) the same with `app.env` `local`, asserting no regression;
      (c) `test_no_public_registration_path_exists` asserting
      `Filament::getPanel('admin')->hasRegistration()` is false and
      `Route::getRoutes()->getByName('register')` is null. Run the suite; confirm (a)
      fails with 403 and (b), (c) already pass.
      **Must go through the HTTP kernel, not `Livewire::test()`** — the existing Filament
      tests bypass middleware, which is why this hole survived 169 green tests (design D7).
- [ ] 1.2 GREEN — `app/Models/User.php`: `implements FilamentUser`, add
      `canAccessPanel(Panel $panel): bool { return true; }` with a docblock naming the
      precondition test as its guard (design D1) **and naming the coming `técnico` role as
      the reason this is a door, not a permission check** (design D9) — so the next reader
      does not "fix" it into an `is_admin` gate that would have to return true for the coach
      anyway.
- [ ] 1.3 Verify — full suite green; re-run `PanelAccessTest` and confirm all three pass.

## Phase 2: Upload hardening — the stored-XSS blocker (TDD)

- [ ] 2.1 RED — Create `tests/Feature/UploadValidationTest.php`: for **both**
      `CreateTeam`/`crest_path` and `CreateNews`/`cover_path` — an `image/svg+xml` upload
      asserts `assertHasFormErrors`, an oversized allowed type asserts the same, and a
      valid PNG within limits asserts `assertHasNoFormErrors`. Use
      `UploadedFile::fake()->create($name, $kb, $mime)`, **not** `->image()`, which would
      build a real raster and prove nothing about MIME handling (design D8). Mirror the
      existing happy path at `TeamResourceTest.php:124-142`. Confirm the two SVG tests fail.
- [ ] 2.2 GREEN — `app/Filament/Resources/Teams/Schemas/TeamForm.php:37` and
      `app/Filament/Resources/News/Schemas/NewsForm.php:31`: add
      `->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])` and
      `->maxSize(2048)`.
      **CONSTRAINT: `->acceptedFileTypes()` must come *after* `->image()`** — `->image()`
      *sets* the wildcard rather than adding to it, so the reverse order silently restores
      `mimetypes:image/*` and re-admits SVG (design D2). GIF is included at the owner's
      request and is safe; **SVG is the exclusion that matters and must stay out.**
- [ ] 2.3 Verify — full suite green, including the pre-existing crest-upload happy path,
      which must still pass unchanged.

## Phase 3: Headers, HTTPS switch, throttle, robots

- [ ] 3.1 RED — Create `tests/Feature/SecurityHeadersTest.php`: a public page response
      carries the four headers; an authenticated `/admin` response carries the same four;
      a plain-HTTP response carries **no** `Strict-Transport-Security`; a request made over
      HTTPS carries HSTS with a non-zero `max-age` and **without** `preload`. Confirm the
      header tests fail.
- [ ] 3.2 GREEN — Create `app/Http/Middleware/SecurityHeaders.php` emitting
      `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
      `Referrer-Policy: strict-origin-when-cross-origin`,
      `Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()`,
      and HSTS **guarded by `$request->secure()`** (design D3).
- [ ] 3.3 `bootstrap/app.php`: `$middleware->append(SecurityHeaders::class)`.
      **CONSTRAINT: global stack, not `$middleware->web(append: ...)`** — Filament's panel
      routes carry their own middleware list (`AdminPanelProvider.php:54-63`) and a
      `web`-scoped registration would leave `/admin` — the surface that matters most —
      without headers.
- [ ] 3.4 `config/app.php`: add `'force_https' => (bool) env('APP_FORCE_HTTPS', false)`.
      `app/Providers/AppServiceProvider.php`: `URL::forceScheme('https')` when that config
      value is true (design D4). Default false, so local dev and the suite are untouched.
- [ ] 3.5 Add a test asserting `force_https` defaults to false and that enabling it makes
      `url()` generate an `https` scheme.
- [ ] 3.6 `routes/web.php`: wrap the five public routes in
      `Route::middleware('throttle:60,1')->group(...)`. Route names must not change —
      `route()` calls in views and `assertSee`-style tests depend on them.
- [ ] 3.7 `public/robots.txt`: `Disallow: /admin`.
- [ ] 3.8 Verify — full suite green. Regression-check `StandingsPageTest`, `FixturesPageTest`,
      `ScorersPageTest`, `NewsPageTest`: all four exercise the routes the throttle group now
      wraps.

## Phase 4: Production configuration and documentation

- [ ] 4.1 Create `.env.production.example`: `APP_ENV=production`, `APP_DEBUG=false`,
      `APP_KEY=` (empty — generated on the target), `APP_FORCE_HTTPS=true`,
      `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `LOG_LEVEL=error`,
      `LOG_STACK=daily`, and every `*_PASSWORD` left empty with a comment.
      **CONSTRAINT: no real credential and no reusable `APP_KEY`.** Do not copy the dev
      `APP_KEY` — it encrypts sessions and cookies.
- [ ] 4.2 Add a test asserting `.env.production.example` exists, sets `APP_DEBUG=false` and
      `APP_ENV=production`, and has an empty `APP_KEY` — the spec requires it to be safe by
      construction, so that is worth a machine check rather than a reviewer's eye.
- [ ] 4.3 Create `DESPLIEGUE.md`: ordered deploy steps (key generation, `migrate --force`
      without `--seed`, `storage:link`, `config:cache`, asset build,
      `make:filament-user`), plus a **"Pendiente — falta elegir hosting"** section listing
      every deferred item with its reason: TLS termination, `->trustProxies()`,
      production nginx (`try_files $uri =404`; no PHP execution under `/storage`),
      production `php.ini` (`display_errors = Off`, `opcache.validate_timestamps = 0`),
      `docker-compose.prod.yml` without the host-published MySQL port, `storage:link`
      regeneration, DB backups, real SMTP, CSP, 2FA, standings caching.
- [ ] 4.3b In the same document, a second section **"Pendiente — trabajo ya decidido"** for
      the follow-ups the owner has already committed to, each with its trigger:
      **(a) record-level policies + per-resource visibility, triggered by the `técnico` role
      the owner says is coming soon** — the highest-priority item on the list, and the
      reason `canAccessPanel()` returns `true` today (design D9);
      (b) `fix(validation)` ranges for `founded_year`, stadium `capacity`,
      `matchdays.number` and string `maxLength`;
      (c) `players.birth_date` — currently unused, unencrypted and never rendered publicly;
      decide whether it is needed at all when something finally uses it.
      Also record the three checklist items **rejected on purpose** so they are not
      re-raised: column encryption, login captcha, and git-history purging (nothing to
      purge — verified across the full history).
- [ ] 4.4 **Verification task**: cross-read `proposal.md`'s two "Out of Scope" lists against
      `DESPLIEGUE.md` and confirm **every** deferred item appears. The spec requires
      traceability, and a silently dropped item is the exact failure mode this phase exists
      to prevent.
- [ ] 4.5 `ARQUITECTURA.md` §8: add "Fase 7 — Producción" to the roadmap; mark Fase 6 done.

## Phase 5: Dependency advisories

- [ ] 5.1 `composer update league/commonmark` — must land ≥ 2.10.0, clearing all four
      advisories (`exploration.md` F9). **CONSTRAINT: this package only.** A bare
      `composer update` would move every dependency including Laravel and Filament, turning
      a security fix into an unreviewable upgrade.
- [ ] 5.2 `composer audit` — must report zero advisories. Paste the actual output into
      `apply-progress.md`; do not assert it from memory.
- [ ] 5.3 `npm install` to rebuild `package-lock.json`, then `npm audit`. The audit
      currently cannot run at all ("Invalid package tree"). Record the result whatever it
      is — including "n findings, not fixed, because X" — rather than silently skipping it.
- [ ] 5.4 Full suite green after the update, plus `npm run build`, since 5.3 touches the
      asset toolchain.
- [ ] 5.5 Add `composer audit` and `npm audit` to `DESPLIEGUE.md`'s pre-deploy steps (task
      4.3), so this becomes a recurring gate rather than a one-off.

### Not in this phase: the seed and the database

The seed reduction planned as unit 5 was **withdrawn by the owner** — *"descartemos eso,
dejemos la bd como está y los datos default también"*. `DatabaseSeeder`, every factory,
`DatabaseSeederTest`, `StandingsServiceTest` and the working database are all untouched, and
**no `migrate:fresh` is run at any point in this change.** Design D6 is marked withdrawn
and retained only as a record.

## Phase 6: Full-suite checkpoint

- [ ] 6.1 `docker compose exec app php artisan test` — target **169 existing tests passing
      unmodified** plus the new security tests, all green. No existing test is adjusted by
      this change, so any existing failure is a genuine regression. Record the actual
      number; do not assume it.
- [ ] 6.2 `./vendor/bin/pint --test` — PSR-12 clean.
- [ ] 6.3 `npm run build` — asset build succeeds.
- [ ] 6.4 Manual check in the browser: log into `/admin`, upload a `.svg` as a team crest
      and confirm it is refused with a validation message; upload a `.png` and confirm it
      still works and renders on the public standings page.
- [ ] 6.5 Confirm via `git diff --stat` that nothing under `database/` was touched —
      migrations, seeders and factories all unchanged — and that no database was re-seeded.
- [ ] 6.6 Confirm `DESPLIEGUE.md` records the two deferred items the owner flagged for the
      near term: **record-level policies, triggered by the arrival of the `técnico` role**
      (the higher priority of the two — `exploration.md` §6), and the `fix(validation)`
      ranges. Neither is implemented here; both must be findable.
