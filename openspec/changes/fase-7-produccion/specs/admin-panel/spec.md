# Delta for admin-panel

## MODIFIED Requirements

### Requirement: Panel Access Policy

`App\Models\User` MUST implement `Filament\Models\Contracts\FilamentUser` and define
`canAccessPanel()`, so that panel access is decided by the application rather than by
Filament's environment-dependent fallback. Every row in the `users` table MUST be granted
access, in any `APP_ENV`.

While `canAccessPanel()` grants access unconditionally, the system MUST NOT expose any
path that creates a user without administrator action: the admin panel MUST NOT enable
Filament registration, and no route named `register` MUST exist. This precondition MUST be
asserted by an automated test, so that enabling either one fails the suite and forces the
access policy to be revisited first.

(Previously — Fase 3: "any authenticated row in the `users` table MUST be able to access
`/admin`, using Filament v5's **default non-production behavior**. The system MUST NOT
implement `canAccessPanel()` role restrictions in this change", with role-based access
"deferred beyond Fase 3, not scheduled to any specific phase". Fase 7 is that phase. The
observable grant is unchanged; what changes is that it no longer depends on `APP_ENV`
being `local`. Filament denies access to a non-`FilamentUser` in every environment except
`local` — `vendor/filament/filament/src/Http/Middleware/Authenticate.php:34-40` — so
without this change the first production deploy 403s the owner out of their own panel.)

This requirement governs **whether a user may open the panel at all**. It deliberately does
not govern what they may see or do once inside. A `técnico` (coach) role with a reduced,
mostly additive set of functions is planned for a following phase; such a user must be able
to open the panel, so restricting them at this layer would be incorrect. Their limits
belong to per-resource visibility and record policies, which remain outside this capability
until that phase specifies them.

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
- THEN the response is successful, exactly as before this change

#### Scenario: No user-creation path exists outside administrator action

- GIVEN the application is fully booted
- WHEN the admin panel's registration setting and the application's named routes are inspected
- THEN panel registration is disabled
- AND no route named `register` is registered
- AND an automated test asserts both, failing if either becomes true
