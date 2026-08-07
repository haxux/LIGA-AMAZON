# admin-panel Specification

## Purpose

Defines installation, admin user provisioning, and access behavior for the Filament v5 admin panel at `/admin`. New capability — Fase 1 (Andamiaje). Role-based panel access restriction is explicitly deferred to Fase 3.

## Requirements

### Requirement: Filament Install Ordering

Filament v5 installation MUST follow this order: `php artisan migrate --seed`, then `composer require filament/filament:"^5.0"`, then `php artisan filament:install --panels`, then `php artisan make:filament-user`.

#### Scenario: Panel installs after DB is ready

- GIVEN the database is migrated and seeded
- WHEN Filament is required via Composer and `filament:install --panels` runs
- THEN `app/Providers/Filament/AdminPanelProvider.php` is generated
- AND no Filament install step runs before migrations complete

### Requirement: Interactive Admin User Creation

The Filament admin user MUST be created interactively per developer via `php artisan make:filament-user`. The system MUST NOT ship a seeder or fixture with hardcoded/seeded admin credentials.

#### Scenario: Developer creates their own admin credentials

- GIVEN `filament:install --panels` has completed
- WHEN a developer runs `php artisan make:filament-user` and enters their own name/email/password
- THEN a user record is created with those credentials
- AND no database seeder or migration file contains hardcoded admin credentials

### Requirement: Panel Access Policy (Demo Scope)

For this change, any authenticated row in the `users` table MUST be able to access `/admin`, using Filament v5's default non-production behavior. The system MUST NOT implement `canAccessPanel()` role restrictions in this change.

#### Scenario: Any authenticated user reaches the panel

- GIVEN a user exists in the `users` table (created via `make:filament-user`)
- WHEN that user logs in at `/admin`
- THEN they are granted access to the Filament panel
- AND no role or permission check blocks access

#### Scenario: Role restriction explicitly deferred

- GIVEN this change (Fase 1) is complete
- WHEN the codebase is reviewed for `canAccessPanel()` logic
- THEN no such restriction exists yet
- AND role-based access control is documented as deferred to Fase 3

### Requirement: Admin Panel Availability

`http://localhost:8080/admin` MUST respond with the Filament login page (200) once the stack is running and Filament is installed.

#### Scenario: Admin login page is reachable

- GIVEN the Docker stack is running and Filament install steps have completed
- WHEN a client requests `http://localhost:8080/admin`
- THEN the response is 200 and renders the Filament login form
