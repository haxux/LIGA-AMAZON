# Proposal: Fase 1 — Andamiaje: Docker (Nginx + PHP-FPM + MySQL), Laravel 13 and Filament v5

> Roadmap phase: **Fase 1 (Andamiaje)** — `ARQUITECTURA.md` §8.

## Intent

Greenfield repo: no `.git`, no `composer.json`/`artisan`, no Docker files. `ARQUITECTURA.md` §6 documents a boot sequence that presumes Laravel already exists and never says how it gets there. Fases 2–5 are blocked until one documented sequence yields a working `/` and `/admin`.

## Scope

### In Scope

- `git init`, `.gitignore`, `.gitattributes` (`eol=lf`) — `.gitattributes` first, before any `.sh` or `git init`.
- One-off scaffold: `docker run --rm -v ${PWD}:/app -w /app composer:2 composer create-project laravel/laravel . "^13.0"`, run **before** `docker compose up -d --build`.
- `docker-compose.yml`: `app` (PHP-FPM), `web` (nginx:alpine, `8080:80`), `db` (mysql:8.0, `33060:3306`), `node` (node:alpine).
- `docker/php/Dockerfile` — pure PHP 8.3-FPM runtime (extensions + Composer binary); never generates app code.
- `docker/nginx/default.conf` — front controller, `fastcgi_pass app:9000`, deny dotfiles.
- `.env` / `.env.example` wired to the `db` service.
- Filament ordering: `migrate --seed` → `composer require filament/filament:"^5.0"` → `filament:install --panels` → `make:filament-user`.

### Out of Scope

- Domain models/migrations → Fase 2 · Filament Resources → Fase 3 · `StandingsService` → Fase 4 · public Blade views → Fase 5
- CI/CD, staging, production deployment

## Capabilities

### New Capabilities

- `local-dev-environment`: Docker stack, boot sequence, env wiring, asset build path.
- `admin-panel`: Filament v5 panel at `/admin` with an authenticated admin user.

### Modified Capabilities

- None (`openspec/specs/` is empty).

## Approach

Exploration Approach 1 (ephemeral Composer container): reproduces §6 almost verbatim, keeps the Dockerfile single-purpose, needs no architecture revision. Rejected: self-bootstrapping entrypoint (idempotency + CRLF complexity); Sail (contradicts the approved `docker/php` + `docker/nginx` layout).

Two §6 gaps closed: a `node` service satisfies `config.yaml` `verify.build_command: "npm run build"` without host Node; MySQL moves off `3306:3306` to avoid host XAMPP/WAMP collisions — `app` reaches `db` internally regardless.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `docker-compose.yml` | New | app/web/db/node |
| `docker/php/Dockerfile` | New | pdo_mysql, bcmath, gd, zip, intl, exif, opcache, pcntl |
| `docker/nginx/default.conf` | New | Front controller → `app:9000` |
| `.gitignore`, `.gitattributes` | New | LF enforcement |
| `composer.json`, `artisan`, `app/`, `public/`, `package.json` | New | Installer-generated |
| `.env`, `.env.example` | New | Mirrors `db` service creds |
| `app/Providers/Filament/AdminPanelProvider.php` | New | From `filament:install` |
| `ARQUITECTURA.md` | Modified | §6: scaffold step, `node`, DB port, Filament ordering |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|-----------|
| UID mismatch → unwritable `storage/`, `bootstrap/cache/` | High | Pin UID/GID in Dockerfile; `chown` after scaffold |
| Nested `.git` from `create-project` | Med | Scaffold, remove nested `.git`, then `git init` |
| CRLF breaking container scripts | Med | `.gitattributes` `eol=lf` committed first |
| Host port collision (8080/33060) | Med | Non-default DB port; overridable via `.env` |
| NTFS bind-mount slowness | Med | Accepted for demo; escape hatch `\\wsl$` |
| Filament v5 / Laravel 13 conflict | Low | Verified compatible; pin `^5.0` |

## Rollback Plan

Low risk — fresh repo, no data or consumers.

1. `docker compose down -v` (containers + MySQL volume) and remove the built `app` image.
2. Delete generated paths: `app/ bootstrap/ config/ database/ public/ resources/ routes/ storage/ tests/ vendor/ node_modules/ composer.* package*.json artisan .env docker/ docker-compose.yml`.
3. `rm -rf .git` if `git init` already ran.
4. Revert the `ARQUITECTURA.md` §6 edit.

Result is the Fase 0 state (`ARQUITECTURA.md`, `.atl/`, `openspec/` untouched). No migration rollback needed — only stock Laravel migrations, dropped with the volume.

## Dependencies

- Docker Desktop (WSL2 backend) on Windows; network access to Packagist, npm, Docker Hub.
- Approved `ARQUITECTURA.md` (Fase 0) as baseline.

## Success Criteria

- [ ] `docker compose up -d --build` starts app/web/db/node with no restart loops.
- [ ] `http://localhost:8080` → Laravel welcome (200); `http://localhost:8080/admin` → Filament login (200).
- [ ] The `make:filament-user` account logs in to `/admin`.
- [ ] `php artisan migrate --seed` runs cleanly; `php artisan test` passes.
- [ ] `npm run build` succeeds inside the `node` service.
- [ ] `git status` clean of `vendor/`, `node_modules/`, `.env`.
