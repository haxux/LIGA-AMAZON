# Verify report: Fase 7 — Endurecimiento para producción

**Verdict**: **PASS WITH WARNINGS**
**Branch**: `fase-7/1-produccion` (8 commits off `main` @ `7df6efc`)
**Suite re-run by this session**: **194 passed, 607 assertions**

Every claim below was re-derived from source or from a live command in this session, not
read back from `apply-progress.md`.

## Task ledger

32 of 33 complete, 1 partial (`5.3`, `npm audit` half). No task claimed done that is not.

## Requirement-by-requirement verification

### Delta: `admin-panel` — Panel Access Policy

| Requirement | Evidence |
|---|---|
| `User implements FilamentUser` with `canAccessPanel()` | `app/Models/User.php:17,43` |
| Access granted in any `APP_ENV` | `PanelAccessTest` — production **and** local cases both green |
| Panel registration disabled, no `register` route, asserted by test | `PanelAccessTest::test_no_user_creation_path_exists_outside_administrator_action` |

Independently re-derived: the defect this fixes is real. Filament's
`Authenticate.php:34-40` denies a non-`FilamentUser` whenever `config('app.env') !== 'local'`,
and apply reproduced the 403 before the fix.

### Delta: `public-file-storage` — Upload restrictions

| Requirement | Evidence |
|---|---|
| Explicit allow-list, no wildcard | `TeamForm.php:46`, `NewsForm.php:40` — jpeg/png/webp/gif |
| SVG excluded | `UploadValidationTest`, both forms |
| Maximum size enforced | `->maxSize(2048)` at `TeamForm.php:49`, `NewsForm.php:43` |
| Size below server limits | 2048 KB vs `client_max_body_size 20M` / `post_max_size 20M` |

**The D2 ordering constraint was checked explicitly, because reversing it fails silently.**
In both files `->image()` precedes `->acceptedFileTypes()` (38→46 and 32→40), so the
wildcard is overridden rather than restored.

Confirmed by the owner through the real Filament UI after apply: uploading a `.svg` raises
the validation error.

### Delta: `production-hardening`

| Requirement | Evidence |
|---|---|
| Four headers on every route | Live `curl`: present on `/` (200) **and** `/admin` (302) |
| Registered globally, not on `web` | `bootstrap/app.php:19` — `$middleware->append()` |
| HSTS only when secure, no `preload` | `SecurityHeaders.php:57`; live `curl` over HTTP shows **0** occurrences |
| HTTPS forcing is a dedicated flag, default off | `config/app.php:71`, `AppServiceProvider.php:28` |
| Public routes limited; panel and health are not | `route:list --json`, verified per route (below) |
| `robots.txt` disallows `/admin` | Served live |
| Production template safe by construction | `ProductionEnvTemplateTest`, 5 tests |
| Deferred items traceable | 19/19, checked by script |

Throttle assignment re-checked route by route against `route:list --json`:

```
OK  /                  ThrottleRequests:60,1     OK  admin        sin límite público
OK  goleadores         ThrottleRequests:60,1     OK  admin/login  sin límite público
OK  noticias           ThrottleRequests:60,1     OK  admin/teams  sin límite público
OK  noticias/{slug}    ThrottleRequests:60,1     OK  up           sin límite público
OK  partidos           ThrottleRequests:60,1
```

## Scope containment

- `git diff main...HEAD -- database/ docker/ .env .env.example` → **empty**. No migration,
  seeder, factory, docker config or environment file touched. No database re-seeded.
- No file outside the areas the proposal predicted.
- Live smoke test against the running container with the existing demo data: `/`,
  `/partidos`, `/goleadores`, `/noticias`, `/admin/login` → all **200**.
- `composer audit`: zero advisories. `npm run build`: green.

## Finding raised by this verify

### ⚠ F10 — `trustProxies` is more load-bearing than the plan assumed

The design treated `->trustProxies()` purely as the companion to `APP_FORCE_HTTPS` (URL
generation). Reading `ThrottleRequests` directly shows a **second** consequence that nothing
in the plan accounted for:

```php
// vendor/laravel/framework/.../ThrottleRequests.php:229
return $this->formatIdentifier($route->getDomain().'|'.$request->ip());
```

For anonymous visitors the rate-limit key is the client IP. Without trusted proxies,
`$request->ip()` resolves to the **proxy's** address, so behind any reverse proxy or CDN the
60 req/min ceiling stops being per-visitor and becomes **a global cap for the whole site** —
it would start returning 429 to everyone under modest traffic. This is the default behaviour
the moment a proxy sits in front, not an edge case.

Not a defect in the applied code: on a direct-to-nginx deployment it behaves correctly, and
the hosting target is still undecided. But it materially raises the priority of a §3 item
that read as routine. `DESPLIEGUE.md` §3.2 was rewritten to state both consequences and to
require the setting before opening to the public behind a proxy.

## Warnings

1. **`npm audit` never ran.** npm 10's `quick` endpoint is retired (400); npm 11's `bulk`
   endpoint returns `503 "We are currently performing maintenance"`. Confirmed external by
   reproducing the identical failure in a clean scratch project. Task `5.3` is marked `[~]`
   rather than `[x]`, and `DESPLIEGUE.md` §3.9 requires a retry before deploy. **The npm
   dependency tree is therefore unaudited.**
2. **Lockfile version drift.** Regenerating `package-lock.json` — to close a real integrity
   gap, 60 of 106 entries having neither `resolved` nor `integrity` — moved 10 dev and
   transitive versions inside their declared semver ranges. Verified green
   (`npm run build`, full suite) and **explicitly accepted by the owner**.
3. **Four pre-existing Pint issues remain** in `bootstrap/providers.php`, `DivisionFactory`,
   `NewsFactory`, `SchemaMigrationTest`. Confirmed pre-existing by running Pint on `main`
   (137 files, 4 issues) and left alone to avoid widening the diff. Informational.
4. **Methodology note, not a code defect.** A check run during this verify initially
   reported all five public routes as unthrottled; the filter was case-sensitive while
   `route:list` prints the resolved class `ThrottleRequests`. Re-run correctly. Recorded
   because the same trap caught task 4.4 during apply (broken shell escaping producing five
   false negatives) — grep-shaped verification in this repo needs its own sanity check.

No CRITICAL issues. Ready for merge and `sdd-archive`.
