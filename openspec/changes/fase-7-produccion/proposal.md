# Proposal: Fase 7 — Endurecimiento para producción + seed reducido

**Roadmap phase**: Fase 7 (new) — beyond `ARQUITECTURA.md`'s original Fase 0–6 roadmap,
which ended at "Pulido". Added because the owner intends to publish the site online.

## Intent

The app is functionally complete and internally sound: Laravel and Filament cover
injection, XSS, CSRF and mass assignment, and the audit (`exploration.md` §1) found no
defect in what the project actually wrote. What is missing is everything that only
matters once the app leaves `localhost`.

Two of those gaps are not merely "hardening". They are blockers:

1. Setting `APP_ENV=production` — the very first deploy step — **locks every user out of
   `/admin` with a 403**, because `User` does not implement `FilamentUser` (F1).
2. Both upload fields accept `image/svg+xml` and serve it from the site's own origin,
   which is a stored-XSS primitive against the admin session (F2).

A third item was added after the owner cross-checked the audit against a 20-point
pre-launch checklist of their own: `composer audit` reports four high-severity advisories
in `league/commonmark` (F9). It is unreachable from this application's code, so it is not
a blocker — but it is a one-package update.

The demo seed is left **exactly as it is**. Emptying it, and then reducing it by 75%, were
both considered and withdrawn by the owner (*"descartemos eso, dejemos la bd como está y
los datos default también"*), so this change touches no data at all.

## Scope

### In Scope

**Security — blockers**

- `User implements FilamentUser` with `canAccessPanel()`, so the panel survives
  `APP_ENV=production`.
- Explicit `acceptedFileTypes()` + `maxSize()` on `TeamForm` and `NewsForm`, replacing the
  bare `->image()` wildcard that admits SVG.

**Security — hosting-agnostic hardening**

- A `SecurityHeaders` middleware on the `web` group: `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and `Strict-Transport-Security`
  emitted only over HTTPS.
- Opt-in HTTPS enforcement driven by config, off by default so local dev is untouched.
- `throttle` on the public routes.
- `robots.txt`: disallow `/admin`.

**Production configuration and documentation**

- `.env.production.example` — a real production file, not a copy of the dev one.
- `DESPLIEGUE.md` — the deploy checklist, including an explicit **"pending: host not yet
  chosen"** section listing every deferred item so nothing is silently lost.
- `ARQUITECTURA.md` roadmap: add Fase 7.

**Dependencies**

- Update `league/commonmark` past 2.10.0, clearing all four advisories.
- Rebuild `package-lock.json` so `npm audit` can run at all, and record its result.
- Add both audit commands to the deploy checklist so this is a recurring check, not a
  one-off.

### Out of Scope — deferred until the hosting target is chosen

The owner has not decided where this deploys ("todavía no lo termino de decidir"). Every
item below has an answer that depends on that choice, so committing one now would mean
committing the wrong one. All are recorded in `DESPLIEGUE.md`, not dropped.

- TLS certificates and termination; whether HTTPS is forced at the app or the proxy.
- `docker-compose.prod.yml` (no host-published MySQL port), production `php.ini`
  (`display_errors = Off`, `opcache.validate_timestamps = 0`).
- nginx production config: the `try_files $uri =404` guard on the PHP location (F6) and a
  no-PHP-execution rule under `/storage`. Meaningless if the host turns out to be Apache
  or a PaaS.
- `artisan storage:link` regeneration on the target (F7).
- DB backup strategy.
- Real SMTP (F8) — only needed if a password-reset flow is ever enabled.

### Out of Scope — deliberate, independent of hosting

- **Content-Security-Policy.** Filament and Livewire emit inline scripts and styles; a
  correct CSP needs nonce propagation through Filament's asset pipeline. High breakage
  risk, disproportionate to a league site. Documented as a follow-up.
- **Two-factor authentication.** Filament v5 supports it; it is an additive feature, not a
  gap in this phase's threat model (single owner-operated account).
- **Roles and permissions.** Still deferred, as since Fase 3. See Open Question 1.
- **Caching the standings computation** (F5). A performance concern surfaced by the audit;
  throttling addresses the abuse angle, caching is a separate change.
- **Any change to the demo seed or the database.** Emptying it and reducing it were both
  proposed and then withdrawn by the owner. `DatabaseSeeder`, every factory, the row counts
  and the working database are untouched, and `DatabaseSeederTest` /
  `StandingsServiceTest` need no adjustment. Emptying would also have produced a 404 on
  `/`, `/partidos` and `/goleadores` (`SiteController.php:33` aborts when no season
  exists) — moot now, but the reason no empty-state work appears here.
- **Record- and resource-level authorization (policies).** Deferred *with a short horizon*:
  the owner states a `técnico` role is coming soon. This is the next phase's work, not an
  omission — see `exploration.md` §6 and design D9.
- **Input-validation ranges.** `founded_year`, stadium `capacity`, `matchdays.number` and
  string `maxLength` are unbounded. Data-integrity defects, not security ones; owner
  deferred them to a separate `fix(validation)` change.
- **Encrypting `players.birth_date`.** Rejected with the owner: player data is public by
  nature, the column is unused, and encrypting it would make it unorderable and
  unfilterable for no threat this project faces.
- **Captcha / bot protection on the login.** Rejected with the owner: the public site has
  no forms, and the panel login already throttles at 5 attempts for a single legitimate
  user.

## Capabilities

### New Capabilities

- `production-hardening`: the deployed application's transport, response-header and
  request-rate posture, plus the production environment template and the record of what
  was deferred pending a hosting decision. Host-specific concerns (TLS termination,
  web-server directives, backups) are explicitly *outside* this capability — they live in
  `DESPLIEGUE.md` as pending decisions until a target is chosen.

No new domain concept is introduced; the league model is untouched.

### Modified Capabilities

- `admin-panel`: the "Panel Access Policy (Demo Scope)" requirement, which explicitly
  forbade `canAccessPanel()`, is replaced by one that requires it. This is the one place
  where Fase 7 overturns a locked earlier decision, by design.
- `public-file-storage`: gains a requirement constraining which file types and sizes may
  be written to the public disk.

Unchanged: `league-data-model` and `public-views` need **no** delta — their seed
requirements are written count-agnostically ("one division's worth of teams", "all
previously-seeded teams"), so the row-count reduction does not touch them
(`exploration.md` §3).

## Approach

Five independent work units, ordered by severity. The two blockers land first and are
individually revertible. Each security change is TDD: a test that fails against today's
code, then the fix.

The hosting-dependent work is not written as "TODO" comments scattered in config files;
it lives in one checklist document, so the deploy decision has a single home.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `app/Models/User.php` | Modified | `implements FilamentUser` + `canAccessPanel()` |
| `app/Filament/Resources/Teams/Schemas/TeamForm.php` | Modified | Explicit MIME allow-list + max size |
| `app/Filament/Resources/News/Schemas/NewsForm.php` | Modified | Explicit MIME allow-list + max size |
| `app/Http/Middleware/SecurityHeaders.php` | New | Response headers |
| `app/Providers/AppServiceProvider.php` | Modified | Opt-in `URL::forceScheme('https')` |
| `bootstrap/app.php` | Modified | Register middleware on the `web` group |
| `config/app.php` | Modified | `force_https` key |
| `routes/web.php` | Modified | `throttle` on public routes |
| `public/robots.txt` | Modified | `Disallow: /admin` |
| `composer.lock` | Modified | `league/commonmark` ≥ 2.10.0 |
| `package-lock.json` | Modified | Rebuilt so `npm audit` can run |
| `tests/Feature/PanelAccessTest.php` | New | F1 regression |
| `tests/Feature/UploadValidationTest.php` | New | F2 regression |
| `tests/Feature/SecurityHeadersTest.php` | New | Header regression |
| `.env.production.example` | New | Production environment template |
| `DESPLIEGUE.md` | New | Deploy checklist + deferred items |
| `ARQUITECTURA.md` | Modified | Roadmap gains Fase 7 |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| `canAccessPanel()` returning `true` becomes an unlocked door if registration is ever added | Med | Regression test asserting no registration route exists and `->registration()` is not enabled on the panel; Open Question 1 offers the stricter alternative |
| MIME allow-list rejects a format the owner actually uses (e.g. an `.ico` or `.avif` crest) | Med | Allow-list is jpeg/png/webp; widening it is a one-line edit, and the failure is a clear validation message, not silent data loss |
| `X-Frame-Options` breaks a Filament feature that frames content | Low | `SAMEORIGIN`, not `DENY`; panel is same-origin throughout |
| HSTS emitted before real TLS exists, pinning browsers to a broken HTTPS | Med | Header is emitted only when `$request->secure()` is true, so it cannot fire over plain HTTP |
| `throttle` trips on legitimate traffic | Low | 60 req/min per IP on read-only pages; panel routes untouched |
| `league/commonmark` update pulls a breaking change | Low | Single transitive package, unused by app code; full suite is the gate |
| A future `técnico` role inherits full panel power because `canAccessPanel()` returns `true` | **High, and expected** | Intentional: the coach *needs* panel access, so the gate is the wrong layer. Restriction belongs in policies and resource visibility — design D9 keeps that path open and the next phase owns it |
| Suite cannot be run to prove any of this | **High** | Docker is unreachable and local PHP is 8.2 (`exploration.md` §4). **Apply is blocked until Docker Desktop's WSL integration is on.** Nothing is claimed green without a real run. |

## Rollback Plan

No migrations, no schema change, and **no data change of any kind** — the database is not
touched, so there is nothing to restore. Each work unit is a separate commit, individually
revertible:

- Reverting unit 1 restores the 403-in-production behaviour (i.e. it re-breaks the panel).
- Reverting unit 2 re-admits SVG uploads.
- Units 3–5 are freely revertible with no security consequence beyond losing the headers.
- The dependency update is revertible with `composer.lock` alone.

`.env` itself is never modified by this change; only a new example file is added.

## Dependencies

- Docker Desktop WSL integration must be enabled before the apply phase can execute
  anything. This is a hard blocker for verification, not for writing the code.

## Success Criteria

- [ ] `APP_ENV=production` no longer 403s an authenticated user out of `/admin`, proven by test.
- [ ] An `image/svg+xml` upload is rejected by both forms, proven by test.
- [ ] The four security headers are present on a public response; HSTS present only over HTTPS.
- [ ] Public routes are throttled; `/admin` is disallowed in `robots.txt`.
- [ ] `.env.production.example` and `DESPLIEGUE.md` exist, and every deferred item from
      "Out of Scope — deferred" appears in the checklist.
- [ ] `composer audit` reports zero advisories; `npm audit` runs and its result is recorded.
- [ ] Full suite green: **169 existing, unchanged**, plus the new security tests.
- [ ] No change under `database/migrations/`, `database/seeders/`, `database/factories/`,
      and no row written to or deleted from any database.

## Open Questions

All resolved with the owner before apply. Recorded as answers, not questions, because each
one shapes a task.

1. **`canAccessPanel()` policy → `return true`, guarded by a no-registration test.**
   Strengthened, not weakened, by the news that a `técnico` role is coming: a coach will
   *need* to reach the panel, so an `is_admin` boolean would have to be granted to them
   anyway and would buy nothing. Panel access is the wrong layer for that distinction —
   policies and per-resource visibility are, and that is the next phase. See design D9.
2. **Seed reduction → withdrawn entirely.** The database and all default data stay as they
   are. Work unit 5 is removed from this change.
3. **Upload allow-list → jpeg, png, webp *and* gif.** The owner asked for GIF. It is safe:
   GIF cannot carry script, and `X-Content-Type-Options: nosniff` (unit 3) closes the
   historical polyglot angle. The exclusion that matters is SVG, which stands.
4. **`players.birth_date` → left as is.** Unused, unencrypted, never rendered publicly.
   Player data is public by nature; revisit if the column is ever given a purpose.
5. **Input-validation ranges → deferred** to a separate `fix(validation)` change.
6. **Record-level access (policies) → deferred, high priority.** Required as soon as the
   `técnico` role lands.
