# Apply progress: Fase 7 — Endurecimiento para producción

**Branch**: `fase-7/1-produccion` (base: `main`, commit `7df6efc`)
**Result**: all tasks done except two blocked externally (5.3 partial, 6.4 partial).
**Suite**: 169 baseline → **194 passing, 607 assertions**. No existing test modified.

## Phase 0 — Precondition

Docker Desktop's WSL integration was enabled by the user mid-session, unblocking apply.
Baseline established before touching anything: **169 passed (458 assertions)**, matching the
number the planning artifacts predicted. No pre-existing failure.

## Phase 1 — Panel access (commit `759693e`)

RED reproduced the defect exactly as designed: `config()->set('app.env', 'production')` +
authenticated `GET /admin` → **403**, while the same request under `local` passed. F1 was
real, not theoretical.

GREEN: `User implements FilamentUser` with `canAccessPanel(): true`. The docblock names both
guards — the no-registration precondition test, and D9's reason this must not become an
`is_admin` check when the `técnico` role lands.

Four tests, all through the HTTP kernel rather than `Livewire::test()`. That distinction is
the whole point: `Livewire::test()` instantiates the page component directly and never runs
panel middleware, which is why 169 green tests never noticed a 403-in-production defect.

## Phase 2 — Upload hardening (commit `00a1419`)

RED produced exactly the predicted split: the 4 rejection tests failed, the 3 acceptance
controls already passed. **The SVG upload succeeded before the fix** — F2 confirmed in a
running application, not inferred from vendor source.

GREEN: `->acceptedFileTypes(['image/jpeg','image/png','image/webp','image/gif'])` +
`->maxSize(2048)` on both fields, placed *after* `->image()` per D2, with the ordering
constraint commented in place so a future edit cannot silently reverse it.

GIF included at the owner's request. The pre-existing crest-upload happy path
(`TeamResourceTest`) still passes unmodified, which is the control that the rule does not
simply reject everything.

## Phase 3 — Headers, HTTPS, throttle, robots (commit `40f61f2`)

RED: 7 failed, 2 passed — the two passing ones were the *absence* assertions (no HSTS over
HTTP, panel not throttled), which correctly held before the change existed.

GREEN: `SecurityHeaders` on the **global** stack per D3. Verified this mattered: the test
asserting headers on `/admin` is separate from the public one precisely because the panel
does not run through the `web` group.

Also `config('app.force_https')` (default false) + `URL::forceScheme()` in
`AppServiceProvider`, `throttle:60,1` around the five public routes, and
`Disallow: /admin` in `robots.txt`.

The 429 test drives 61 real requests rather than asserting middleware presence alone, so it
proves the limit fires rather than that a string appears in a middleware list.

## Phase 5 — Dependencies (commit `51aea07`) — run before Phase 4

Reordered deliberately: doing dependencies first meant `DESPLIEGUE.md` could record real
audit output instead of predicted output.

`composer update league/commonmark` → **2.9.0 → 2.10.1**. Verified the blast radius:
`git diff composer.lock` shows **12 lines, one package, zero packages added or removed**.
`composer audit` now reports **zero advisories** (was 4 high).

**`npm audit` remains unresolved, blocked externally.** Investigated properly rather than
skipped:

1. `package-lock.json` had **60 of 106 entries with neither `resolved` nor `integrity`** —
   built from a pre-populated `node_modules` instead of from the registry, so `npm ci` could
   not verify any package's integrity. Regenerated from the registry; all 107 entries now
   carry both.
2. The audit **still** failed identically. Hypothesis tested and discarded.
3. Reproduced the identical 400 in a **clean scratch project** with a valid `name` and
   `version` → not a project defect.
4. `npx npm@11 audit`, which uses the bulk endpoint, returned **503 "We are currently
   performing maintenance"**. Definitive: npm's audit service is down.

The lockfile regeneration moved 10 transitive/dev versions inside their declared semver
ranges (`vite` 8.2.1→8.3.0, `laravel-vite-plugin` 3.1.3→3.2.0, +8). Kept rather than
reverted because the integrity gap it closes is a genuine supply-chain control, and verified
rather than assumed: `npm run build` green, full PHP suite green.

## Phase 4 — Production config and docs (commits `c6698b2`, `a994ab6`)

`.env.production.example` + `ProductionEnvTemplateTest`, which **earned its keep
immediately**: it failed on first run because this file's own explanatory comment contained
the literal string `APP_DEBUG=true`. A reviewer would very plausibly have skimmed past that.

`DESPLIEGUE.md` in five sections: what is already protected (§1), deploy steps (§2), pending
on the hosting decision (§3), follow-ups already committed to (§4), rejected on purpose (§5).

Task 4.4's verification was run as an actual script, not by eye — and caught a false
negative in my first attempt, where shell escaping broke the alternation and reported five
present items as missing. Re-run correctly: **all 19 deferred and rejected items traceable.**

## Phase 6 — Checkpoint

- Full suite: **194 passed (607 assertions)**. 169 baseline + 4 + 7 + 9 + 5 new.
- `pint --test`: the one new issue in this branch was fixed (`c6698b2`). Four issues remain
  in `bootstrap/providers.php`, `DivisionFactory`, `NewsFactory` and `SchemaMigrationTest` —
  **confirmed pre-existing by running pint on `main`** (137 files, 4 issues). Left alone
  rather than widening the diff; reported to the owner.
- `npm run build`: green.
- **Live verification against the running container**, not only the suite: all four headers
  present on `http://localhost:8080/` (200) *and* `/admin` (302); `Strict-Transport-Security`
  correctly absent over plain HTTP; `robots.txt` served with `Disallow: /admin`.
- `git diff --stat main...HEAD -- database/` → **empty**. No migration, seeder, factory or
  row touched, and no `migrate:fresh` run at any point.
- Diff: 18 files, 1290 insertions. Excluding `package-lock.json` (840) and documentation
  (270), roughly **380 lines of code and tests** — under the 400-line budget as forecast.

## Not done, and why

- **`npm audit` (task 5.3)** — npm's audit service returns 503. External, reproduced in a
  clean project. Recorded in `DESPLIEGUE.md` §3.9 as a pre-deploy step to retry.
- **Manual browser upload check (task 6.4)** — the automated equivalent
  (`UploadValidationTest`, 7 tests) covers SVG rejection, oversize rejection and PNG/GIF
  acceptance on both forms, and the live header check was done by curl against the running
  container. Driving the Filament UI by hand was not performed; the owner should do the
  SVG-upload check once in the browser before deploying.

## Next

`sdd-verify`. Nothing is merged — the branch is left for review, matching the Fase 2–6
pattern.
