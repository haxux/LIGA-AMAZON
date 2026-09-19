# Archive Report: Fase 7 — Producción

**Change**: `fase-7-produccion`
**Archived**: 2026-09-19
**Archive location**: `openspec/changes/archive/2026-09-19-fase-7-produccion/`
**Status**: Complete — all phases done, main specs merged, branch merged to `main`

## Executive Summary

Fase 7 hardened the application for public deployment. It was added beyond
`ARQUITECTURA.md`'s original Fase 0–6 roadmap because the owner intends to publish the site.

Two of the findings were not hardening but **blockers**, and both were confirmed real in a
running application rather than inferred from source:

- **F1** — `User` did not implement `FilamentUser`, so Filament's environment fallback
  returned **403 to every user** the moment `APP_ENV` stopped being `local`. Reproduced
  exactly: the first production deploy would have locked the owner out of their own panel.
- **F2** — both upload fields accepted `image/svg+xml` and served it same-origin from
  `/storage/`, a stored-XSS primitive against the admin session. Reproduced: **the SVG
  upload succeeded before the fix.**

32 of 33 tasks complete; one partial (`5.3`, blocked on npm's audit service). Suite went
from a 169 baseline to **194 passing (607 assertions) with zero existing tests modified**.
Verify returned PASS WITH WARNINGS (0 CRITICAL) and raised one new finding of its own (F10).

The change also answered the owner's own 20-point pre-launch checklist item by item: 7
already satisfied, 5 acted on, 4 deferred with reasons, 4 rejected as inapplicable to this
stack.

## Artifacts Archived

1. **exploration.md** — Audit of 13 pre-existing controls before 9 findings, so scope
   targeted real gaps. Includes §3 (seed-reduction analysis, marked superseded), §5 (the
   20-point checklist verdict table) and §6 (forward constraints stated by the owner).
2. **proposal.md** — Three-way scope split: in-scope, deferred-pending-hosting, and
   deliberately-out-of-scope. Six open questions, all resolved with the owner before apply.
3. **design.md** — 9 decisions. D6 **withdrawn** (seed reduction) and retained as record;
   D9 added after the owner disclosed the coming `técnico` role.
4. **tasks.md** — 6 phases, 33 tasks. 32 `[x]`, one `[~]` partial.
5. **specs/admin-panel/spec.md** — MODIFIED delta.
6. **specs/public-file-storage/spec.md** — ADDED delta.
7. **specs/production-hardening/spec.md** — New capability delta, 7 requirements.
8. **apply-progress.md** — Phase-by-phase execution log.
9. **verify-report.md** — PASS WITH WARNINGS, 4 warnings, 1 new finding.
10. **state.yaml** — Full lifecycle record and locked decisions.

## Spec Merge Summary

Three canonical specs changed.

**`openspec/specs/admin-panel/spec.md` — one requirement REPLACED.**
"Panel Access Policy (Demo Scope)" is gone, along with its scenario "Role restriction
explicitly deferred beyond Fase 3". This is the one place Fase 7 overturned a locked earlier
decision, deliberately: Fase 3 wrote that the system "MUST NOT implement `canAccessPanel()`"
and deferred the restriction "beyond Fase 3, not scheduled to any specific phase" — Fase 7
was that phase. **Before this merge the canonical spec actively contradicted the shipped
code.** The replacement records the superseded text as a quoted history paragraph so the
reversal is auditable rather than silent, and states that the requirement governs only
*whether* a user opens the panel, not what they may do inside.

**`openspec/specs/public-file-storage/spec.md` — one requirement ADDED.**
Upload type and size restriction, naming the SVG exclusion and its reason. No existing
requirement touched.

**`openspec/specs/production-hardening/spec.md` — NEW capability, 7 requirements.**
Headers, HSTS gating, the HTTPS switch, rate limiting, robots, the production env template,
and the requirement that deferred host-specific work stays recorded. Its Purpose explicitly
excludes host-specific concerns so a later hosting decision will not require amending it.

## Carried Forward

Nothing was dropped. Every open item lives in `DESPLIEGUE.md`:

- **§3 — pending the hosting decision**: TLS termination, `trustProxies`, production nginx,
  production `php.ini`, `docker-compose.prod.yml`, `storage:link`, DB backups, real SMTP,
  CSP, and the `npm audit` retry.
- **§4 — already committed to**: ⭐ record-level policies (trigger: the `técnico` role),
  validation ranges, `players.birth_date`, standings caching, 2FA.
- **§5 — rejected on purpose**: column encryption, login captcha, git-history purging, RLS /
  "public DB key", hiding API keys.

### Finding raised during verify

**F10 — `trustProxies` carries a second, undocumented consequence.** `ThrottleRequests`
derives its key from `$request->ip()` for anonymous visitors
(`ThrottleRequests.php:229`). Without trusted proxies, that resolves to the *proxy's*
address, so behind any reverse proxy or CDN the 60 req/min ceiling becomes a **global cap for
the whole site** rather than per-visitor, returning 429 to everyone under modest traffic. Not
a defect in the shipped code — correct on a direct-to-nginx deploy — but it turns a routine
§3 item into a prerequisite. `DESPLIEGUE.md` §3.2 was rewritten accordingly.

## Known Gaps at Archive Time

1. **The npm dependency tree is unaudited.** npm's audit service returns 503; confirmed
   external by reproducing the identical failure in a clean scratch project. Retry required
   before deploy (`DESPLIEGUE.md` §3.9). `composer audit` is clean.
2. **Four pre-existing Pint issues** remain in files this change does not touch, confirmed
   present on `main` before the branch.
3. **The application has never run behind TLS or a proxy.** `APP_FORCE_HTTPS`,
   `SESSION_SECURE_COOKIE` and HSTS are correct by construction and covered by tests, but
   their real behaviour is unverified until a host exists — which is precisely why F10 was
   found by reading source rather than by observation.

## Traceability

- Branch `fase-7/1-produccion`, 8 commits, fast-forwarded into `main` (`7df6efc` →
  `f248184`), keeping the repository's linear history with no merge commits.
- Scope containment verified: `git diff main...HEAD -- database/ docker/ .env .env.example`
  is empty. No migration, seeder, factory, docker config or environment file touched, and no
  database re-seeded — the owner withdrew the seed change mid-planning.
- Final state on `main`: 194 tests passing, `composer audit` clean, `npm run build` green,
  headers verified live on both the public site and `/admin`.
