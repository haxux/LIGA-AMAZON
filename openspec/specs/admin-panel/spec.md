# admin-panel Specification

## Purpose

Defines installation, admin user provisioning, and access behavior for the Filament v5 admin panel at `/admin`. New capability — Fase 1 (Andamiaje). Panel access became an application decision in Fase 7 (Producción); role- and record-level authorization remains deferred, with the arrival of the `técnico` role as its trigger.

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

### Requirement: Panel Access Policy

`App\Models\User` MUST implement `Filament\Models\Contracts\FilamentUser` and define
`canAccessPanel()`, so that panel access is decided by the application rather than by
Filament's environment-dependent fallback, in any `APP_ENV`.

Since Fase 9 there are two panels and the decision is **which** one a user may open, not
whether they may open one: `admin` for the administrator role, `club` for the coach, and
neither for anything else. This is still not the permission check — what a coach may touch
inside their own panel is decided by record policies (see below).

While `canAccessPanel()` grants access unconditionally, the system MUST NOT expose any path
that creates a user without administrator action: the admin panel MUST NOT enable Filament
registration, and no route named `register` MUST exist. This precondition MUST be asserted
by an automated test, so that enabling either one fails the suite and forces the access
policy to be revisited first.

This requirement governs **which panel a user may open**. It deliberately does not govern
what they may see or do once inside.

### Requirement: A coach may only touch their own club

The system MUST restrict the coach role to the records of the club they are assigned to, at
two independent layers, because either one alone leaves a hole:

1. **Separate panels.** The administrator's resources MUST NOT be registered on the coach's
   panel. Hiding a resource inside one shared panel would leave its route alive, and typing
   the URL would reach it.
2. **Record policies.** Every model that belongs to a club — the club, its season entries,
   its players, its squad memberships and its stadium — MUST answer `view`, `update` and
   `delete` by comparing the record's club against the user's. The administrator MUST pass
   everywhere. This is what covers a URL typed by hand inside the coach's own panel.

A squad membership MUST be governed by the club fielding the player, not the club that owns
them: on a loan, the squad is built by whoever puts the player on the pitch.

A coach MUST be assigned exactly one club, and a club MUST have at most one coach. A coach
without a club, or an administrator with one, MUST be rejected when saved.

#### Scenario: A coach cannot reach another club's records

- GIVEN a coach assigned to club A and a player of club B
- WHEN authorization is checked for updating or deleting that player
- THEN it is denied, while the same check for a player of club A is granted

#### Scenario: Each role opens its own panel only

- GIVEN a coach and an administrator
- WHEN each requests the other's panel
- THEN the response is forbidden, and each reaches their own

(History — Fase 3 specified the inverse: "any authenticated row in the `users` table MUST be
able to access `/admin`, using Filament v5's **default non-production behavior**. The system
MUST NOT implement `canAccessPanel()` role restrictions in this change", with role-based
access "deferred beyond Fase 3, not scheduled to any specific phase". Fase 7 was that phase.
The observable grant is unchanged; what changed is that it no longer depends on `APP_ENV`
being `local`. Filament denies access to a non-`FilamentUser` in every environment except
`local` — `vendor/filament/filament/src/Http/Middleware/Authenticate.php:34-40` — so without
this the first production deploy returns 403 to the owner on their own panel.)

#### Scenario: Authenticated user reaches the panel in production

- GIVEN a user exists in the `users` table
- AND `APP_ENV` is `production`
- WHEN that user requests `/admin` while authenticated
- THEN the response is successful and the panel renders
- AND no 403 is returned

#### Scenario: Authenticated user still reaches the panel locally

- GIVEN a user exists in the `users` table
- AND `APP_ENV` is `local`
- WHEN that user requests `/admin` while authenticated
- THEN the response is successful

#### Scenario: No user-creation path exists outside administrator action

- GIVEN the application is fully booted
- WHEN the admin panel's registration setting and the application's named routes are inspected
- THEN panel registration is disabled
- AND no route named `register` is registered
- AND an automated test asserts both, failing if either becomes true

### Requirement: Admin Panel Availability

`http://localhost:8080/admin` MUST respond with the Filament login page (200) once the stack is running and Filament is installed.

#### Scenario: Admin login page is reachable

- GIVEN the Docker stack is running and Filament install steps have completed
- WHEN a client requests `http://localhost:8080/admin`
- THEN the response is 200 and renders the Filament login form
