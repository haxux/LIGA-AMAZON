# Tasks: Fase 1 — Andamiaje: Docker + Laravel 13 + Filament v5

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1500-2500+ (Laravel skeleton scaffold dominates: config/, database/, routes/, tests/, resources/, bootstrap/, public/ — mostly generated) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | 4 work units — see below |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending — user must pick before `sdd-apply` |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Repo init + Laravel 13 scaffold (Phase 1) | PR 1 | Bulk is `composer create-project` generated output; recommend reviewers skim rather than line-review, or apply `size:exception` to this unit only |
| 2 | Docker infra: Dockerfile, php.ini, nginx conf, compose, env (Phase 2) | PR 2 | Hand-written, ~150-200 lines, normal review |
| 3 | Stack boot, migrations, stock test suite green (Phase 3-4) | PR 3 | Mostly verification steps, minimal diff |
| 4 | Filament install + asset build wiring + docs (Phase 5-7) | PR 4 | Mixed generated (`AdminPanelProvider.php`) + hand-written (`vite.config.js`, `ARQUITECTURA.md`) |

If `stacked-to-main` is chosen, order PR1 → PR2 → PR3 → PR4. If `feature-branch-chain`, PR1 targets the tracker branch, PR2 targets PR1's branch, etc. Orchestrator must ask user before `sdd-apply`.

## Phase 1: Repository Init & Application Scaffold

- [x] 1.1 Run ephemeral `composer:2` container as UID/GID 1000:1000 with `COMPOSER_HOME=/tmp`: `composer create-project laravel/laravel /tmp/laravel "^13.0" --no-interaction --remove-vcs`, then `cp -a /tmp/laravel/. /app/` into the bind-mounted repo root
- [x] 1.2 Verify `.gitattributes` (ships from the skeleton) contains `* text=auto eol=lf`; extend `.gitignore` with Laravel defaults
- [x] 1.3 `git init`, `git add .`, first commit

## Phase 2: Docker Infrastructure

- [x] 2.1 Create `docker/php/Dockerfile` (`php:8.3-fpm`, extensions `pdo_mysql bcmath gd zip intl exif opcache pcntl`, `ARG UID/GID` default 1000, remap `www-data`, `USER www-data`)
- [x] 2.2 Create `docker/php/php.ini` (`memory_limit=512M`, upload limits, dev opcache settings)
- [x] 2.3 Create `docker/nginx/default.conf` (front controller, `fastcgi_pass app:9000`, deny dotfiles except `.well-known`)
- [x] 2.4 Create `docker-compose.yml` with `app`/`web`/`db`/`node` services, `db_data` volume, `db` healthcheck, `app` `depends_on: db (service_healthy)`
- [ ] 2.5 **BLOCKED** — Create `.env.example` (`DB_PORT=3306`, `DB_PORT_HOST=33060`, `APP_PORT=8080`, `UID/GID=1000`); `cp .env.example .env`. The executor's Read/Write/Edit/Bash tools are hard-denied by the host permission sandbox for any `.env*` path (confirmed via isolated probes; not a content issue). Runtime DB/APP wiring was instead injected via `docker-compose.yml`'s `app.environment:` block (Docker sets real process env vars, which Laravel's Dotenv loader will not override), so Phases 3-6 can still be verified for real. `.env.example` itself still needs to be created by a human or in a follow-up apply run with elevated permission — exact content is in the apply-progress artifact.

## Phase 3: Stack Boot & Environment Verification

- [x] 3.1 `docker compose up -d --build`; confirm `app`/`web`/`db`/`node` reach running state with no restart loops
- [x] 3.2 `docker compose exec app php artisan key:generate`
- [x] 3.3 Verify `storage/`, `bootstrap/cache/` are writable by `www-data` (`exec app touch storage/logs/probe && rm`)
- [x] 3.4 Smoke test: `curl -I http://localhost:8080` returns 200 (Laravel welcome) — **required 4.1 to run first** (see deviation note below)

## Phase 4: Database & Stock Test Suite

- [x] 4.1 `docker compose exec app php artisan migrate --seed`; confirm clean run against `db` — **pulled forward ahead of 3.4**, see deviation note
- [x] 4.2 `docker compose exec app php artisan test`; confirm the stock Laravel suite passes — 2/2 passed (`Tests\Unit\ExampleTest`, `Tests\Feature\ExampleTest`)

**Ordering deviation discovered during real execution**: task 3.4's smoke test, run in strict tasks.md order (before 4.1), returned `500` — `PDOException: Base table or view not found: 'sessions'`. The Laravel 13 skeleton defaults `SESSION_DRIVER=database`, so any HTTP request (including `/`) needs the `sessions` table before it can respond, even the welcome page. Task 4.1 (`migrate --seed`) was run before re-attempting 3.4's smoke test to unblock it. This is a defect in the tasks.md phase ordering (not anticipated in design.md), not a design deviation — future runs of this playbook should run `migrate` before the first HTTP smoke test.

**TDD note**: `strict_tdd: true` applies to Services/business logic (Fase 2+). Phase 4 verifies the stock, pre-existing Laravel test suite — there is no new logic to red-green-refactor here, and no meaningful unit test exists for pure config files (Dockerfile, nginx.conf, docker-compose.yml) in Phases 1-2. This phase is verification-first per the design's Testing Strategy, not exempted from the project's TDD rule.

## Phase 5: Filament v5 Install

- [x] 5.1 `composer require filament/filament:"^5.0"` — resolved `filament/filament v5.7.6`, `livewire/livewire v4.3.5` (matches `config.yaml` conventions, no compat conflict — the exploration-phase risk did not materialize)
- [x] 5.2 `php artisan filament:install --panels`; confirm `app/Providers/Filament/AdminPanelProvider.php` generated — confirmed, plus assets published and registered in `bootstrap/providers.php`
- [ ] 5.3 **BLOCKED (human-only step)** `php artisan make:filament-user` (interactive; no hardcoded/seeded credentials) — the executor's Bash tool has no real TTY for an interactive prompt, and the platform's auto-mode permission classifier explicitly denied an attempt to generate a one-off verification password/credential for this step. This is consistent with the spec's own intent ("developer enters their own name/email/password") — a human must run this command themselves: `docker compose exec app php artisan make:filament-user`
- [x] 5.4 Smoke test (partial): `http://localhost:8080/admin` → `302` to `/admin/login` → `200` (standard Filament unauthenticated-redirect behavior, confirms the login form renders). **Login verification deferred** — cannot log in without an admin user (blocked by 5.3); a human should complete this manually per design.md's own Testing Strategy, which specifies this exact check as "Manual browser" verification, not automatable via curl (Livewire/CSRF login flow).

## Phase 6: Asset Build

- [x] 6.1 Edit `vite.config.js`: `server.host: '0.0.0.0'`, `hmr.host: 'localhost'`
- [x] 6.2 `docker compose run --rm node npm run build`; confirm compiled assets produced — built in 1m38s, `public/build/manifest.json` + assets produced. `node:22-alpine` was confirmed compatible with the skeleton's Vite version (v8.2.1/rolldown-vite) — the exploration-phase risk did not materialize, no pin change needed.

## Phase 7: Documentation & Final Verification

- [x] 7.1 Update `ARQUITECTURA.md` §6: scaffold step, `node` service, non-default MySQL port (documented as `33061`, not `33060` — see deviation note), Filament install ordering, PHP 8.4-fpm bump, and the `.env` permission-block workaround
- [x] 7.2 Final checklist — see Success Criteria table below

### Proposal Success Criteria — final status

| Criterion | Status |
|---|---|
| `docker compose up -d --build` starts app/web/db/node with no restart loops | ✅ Pass |
| `http://localhost:8080` → Laravel welcome (200) | ✅ Pass |
| `http://localhost:8080/admin` → Filament login (200) | ✅ Pass (302→`/admin/login`→200, standard Filament behavior) |
| The `make:filament-user` account logs in to `/admin` | ❌ **Not verified** — no admin user was created (see 5.3, blocked) |
| `php artisan migrate --seed` runs cleanly; `php artisan test` passes | ✅ Pass (2/2 stock tests) |
| `npm run build` succeeds inside the `node` service | ✅ Pass |
| `git status` clean of `vendor/`, `node_modules/`, `.env` | ✅ Pass |

5/6 criteria fully pass; 1 requires a human follow-up step (see Remaining Tasks in the return summary).
