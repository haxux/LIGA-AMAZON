# local-dev-environment Specification

## Purpose

Defines the Dockerized local development environment for Liga Amazon: repository bootstrap, application scaffold, the `app`/`web`/`db`/`node` service stack, environment wiring, and the boot sequence that yields a working `http://localhost:8080`. New capability — Fase 1 (Andamiaje).

## Requirements

### Requirement: Repository Initialization Order

The repository MUST commit `.gitattributes` with `eol=lf` before any `.sh` script executes or `git init` runs, to prevent CRLF corruption of container scripts.

#### Scenario: Line endings enforced before scaffold

- GIVEN an empty working directory
- WHEN the repo is bootstrapped
- THEN `.gitattributes` (with `eol=lf`) and `.gitignore` exist before `git init` is executed
- AND no `.sh` file is committed with CRLF endings

### Requirement: Application Scaffold via Ephemeral Composer Container

The system MUST scaffold Laravel 13 using a one-off `composer:2` container (`docker run --rm -v ${PWD}:/app -w /app composer:2 composer create-project laravel/laravel . "^13.0"`) run BEFORE `docker compose up -d --build`. The scaffold MUST NOT leave a nested `.git` directory.

#### Scenario: Scaffold precedes stack boot

- GIVEN Docker Desktop is running and the working directory is empty of app code
- WHEN the ephemeral Composer container runs the create-project command
- THEN `composer.json`, `artisan`, `app/`, `public/`, `package.json` exist in the working directory
- AND any nested `.git` created by the installer is removed before the outer `git init`

### Requirement: Docker Compose Stack Composition

`docker-compose.yml` MUST define exactly four services: `app` (PHP-FPM 8.4), `web` (nginx:alpine, published on `8080:80`), `db` (mysql:8.0, published on a non-default host port `33061:3306`), and `node` (node:alpine).

#### Scenario: Stack starts without restart loops

- GIVEN the scaffold and Docker files are in place
- WHEN `docker compose up -d --build` runs
- THEN all four services (`app`, `web`, `db`, `node`) reach a running state
- AND none of them enters a restart loop

### Requirement: PHP Runtime Image

`docker/php/Dockerfile` MUST build a pure PHP 8.4-FPM runtime (required extensions + Composer binary) and MUST NOT generate or embed application code.

#### Scenario: PHP image is code-agnostic

- GIVEN `docker/php/Dockerfile` builds successfully
- WHEN the resulting `app` container starts before any app code is mounted
- THEN it exposes `php-fpm` on port 9000 with `pdo_mysql`, `bcmath`, `gd`, `zip`, `intl`, `exif`, `opcache`, `pcntl` available

### Requirement: Nginx Front Controller

`docker/nginx/default.conf` MUST route requests through Laravel's front controller, proxy PHP requests to `app:9000` via `fastcgi_pass`, and deny access to dotfiles.

#### Scenario: Web root serves Laravel, blocks dotfiles

- GIVEN the `web` and `app` services are running
- WHEN a client requests `http://localhost:8080`
- THEN nginx forwards PHP execution to `app:9000` and returns the Laravel welcome page (200)
- AND a request to a dotfile path (e.g. `/.env`) is denied

### Requirement: Environment Configuration

`.env` and `.env.example` MUST be wired to the `db` service's internal hostname/port (`db:3306`) regardless of the host-published port, and the host DB port MUST be overridable via `.env` to avoid local collisions.

#### Scenario: App reaches DB internally on non-default host port

- GIVEN `db` publishes `33061:3306` on the host
- WHEN the `app` container connects to the database
- THEN it connects via the internal Docker network address `db:3306`, independent of the host port mapping

### Requirement: Asset Build via Node Service

The `node` service MUST run `npm run build` successfully without requiring Node installed on the host.

#### Scenario: Build runs inside the container

- GIVEN the `node` service is running with `package.json` present
- WHEN `npm run build` is executed inside the `node` service
- THEN the build completes successfully and produces compiled assets

### Requirement: Boot Sequence and Test Suite Success

After scaffold, stack boot, and `.env` wiring, `php artisan migrate --seed` MUST run cleanly and the stock Laravel test suite (`php artisan test`) MUST pass.

#### Scenario: Migrations and stock tests pass

- GIVEN the `app` container is running and connected to `db`
- WHEN `php artisan migrate --seed` then `php artisan test` are run
- THEN migrations complete without error
- AND the stock Laravel test suite passes

### Requirement: Architecture Documentation Sync

`ARQUITECTURA.md` §6 MUST be updated in this change to document the scaffold step, the `node` service, the non-default MySQL host port, and the correct Filament install ordering.

#### Scenario: §6 reflects the real boot sequence

- GIVEN Fase 1 is complete
- WHEN a developer reads `ARQUITECTURA.md` §6
- THEN it describes, in order: scaffold via ephemeral Composer container, `docker compose up -d --build`, the `node` service's role, the non-default MySQL port, and the Filament install ordering
