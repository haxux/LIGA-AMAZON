# Production Hardening Specification

## Purpose

Defines the deployed application's transport, response-header and request-rate posture, and
the production environment template that configures it. New capability — Fase 7 (Producción).

Host-specific concerns are deliberately **outside** this capability: TLS termination, trusted
proxies, web-server directives, production PHP settings, database backups and
Content-Security-Policy all have answers that depend on a hosting target that was undecided
when this capability was written. They are recorded as pending decisions in `DESPLIEGUE.md`
so a later hosting choice does not require amending this spec.

## Requirements


### Requirement: Hardening response headers on every route

Every HTTP response, from both the public site and the admin panel, MUST carry
`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, a `Referrer-Policy` that
does not leak full URLs cross-origin, and a `Permissions-Policy` denying camera,
microphone and geolocation.

The middleware providing these MUST be registered on the global stack, not the `web`
group: the admin panel's routes run on the panel's own middleware list and would otherwise
be left uncovered.

#### Scenario: A public page response carries the headers

- GIVEN the application is running
- WHEN any public page is requested
- THEN the response carries all four headers

#### Scenario: An admin panel response carries the same headers

- GIVEN an authenticated administrator
- WHEN they request a page under `/admin`
- THEN the response carries all four headers

### Requirement: HSTS is emitted only over a secure connection

`Strict-Transport-Security` MUST be sent when, and only when, the request was received
over HTTPS. It MUST NOT include the `preload` directive.

Emitting HSTS over plain HTTP, or before a valid certificate is in place, instructs every
visiting browser to refuse HTTP for the declared lifetime — an outage that cannot be
withdrawn by removing the header. Gating on the connection's actual scheme makes that
state unreachable.

#### Scenario: Plain HTTP response omits HSTS

- GIVEN the application is reached over plain HTTP
- WHEN any page is requested
- THEN the response does not carry `Strict-Transport-Security`

#### Scenario: HTTPS response carries HSTS without preload

- GIVEN the application is reached over HTTPS
- WHEN any page is requested
- THEN the response carries `Strict-Transport-Security` with a non-zero `max-age`
- AND the header does not contain `preload`

### Requirement: HTTPS URL generation is an explicit, reversible switch

Forcing generated URLs to the `https` scheme MUST be controlled by a dedicated
configuration value, defaulting to disabled. It MUST NOT be inferred from `APP_ENV`.

Inferring it from the environment couples two unrelated decisions: it prevents running a
production-like environment locally, and behind a TLS-terminating proxy whose forwarded
headers are not trusted it produces a redirect loop that can only be broken by a code
change. A dedicated flag is reversible by editing one environment variable.

#### Scenario: Disabled by default

- GIVEN the HTTPS-forcing configuration value is unset
- WHEN the application generates a URL
- THEN the URL uses the scheme of the incoming request, unchanged from prior behaviour

#### Scenario: Enabled by environment

- GIVEN the HTTPS-forcing configuration value is enabled
- WHEN the application generates a URL
- THEN the URL uses the `https` scheme

### Requirement: Public routes are rate limited, panel and health check are not

The public read-only routes MUST be served behind a per-IP request-rate limit. The admin
panel MUST NOT carry that limit — Filament already throttles the authentication endpoint,
and a blanket limit would fire during legitimate bulk data entry. The health-check
endpoint MUST NOT be limited, so that uptime monitoring is never throttled.

#### Scenario: Excessive public requests are limited

- GIVEN a client exceeding the configured per-minute request budget
- WHEN it requests a public page again within the same window
- THEN the response is HTTP 429

#### Scenario: Panel routes are unaffected

- GIVEN an authenticated administrator performing many panel requests in a short period
- WHEN those requests exceed the public route budget
- THEN no request is rejected with HTTP 429 by the public rate limit

### Requirement: The admin panel is excluded from search indexing

`public/robots.txt` MUST disallow `/admin`.

#### Scenario: robots.txt disallows the panel

- GIVEN `public/robots.txt` is served
- WHEN its contents are read
- THEN they contain a `Disallow` rule covering `/admin`

### Requirement: A production environment template exists and is distinct from the development one

The repository MUST ship a production environment template separate from `.env.example`.
It MUST set `APP_ENV=production`, `APP_DEBUG=false`, an empty `APP_KEY` to be generated on
the target, `SESSION_SECURE_COOKIE=true`, a non-debug `LOG_LEVEL`, a rotating log stack,
and MUST NOT contain any real credential or reusable application key.

Reusing `.env.example` is not sufficient: it is a development file that sets
`APP_DEBUG=true`, which renders stack traces and the full environment — database
credentials included — to any visitor who triggers an uncaught exception.

#### Scenario: Template is safe by construction

- GIVEN the production environment template
- WHEN its contents are read
- THEN `APP_DEBUG` is `false` and `APP_ENV` is `production`
- AND `APP_KEY` is empty
- AND no real password, secret or key value appears in it

### Requirement: Schema migrations are applied by the container entrypoint

The container entrypoint MUST apply outstanding migrations on boot, without prompting and
without seeding, and MUST take a lock shared across instances while doing so — the host may
start several at once. The lock store MUST therefore be one every instance can see; the
production template pins the cache store to the database for this reason. Only a database
with no lock table yet MAY be migrated unlocked.

The step MUST be retried a bounded number of times before giving up, because the host scales
to zero and the container therefore boots many times a day: without retries a momentary blip
of the managed database during one cold start would take the whole site down rather than one
page. Once the attempts are spent the entrypoint MUST stop the container rather than serve
against a schema it could not reach — the failure this replaces was silent, a page answering
200 with no data because Eloquent read a missing column as null.

A rollback MUST remain a deliberate manual act, and the deployment document MUST carry the
command: rolling the code back does not roll the schema back.

#### Scenario: A deploy carrying a migration needs no manual step

- GIVEN a deploy whose code expects a column the database does not have
- WHEN the container boots
- THEN the migration is applied before the first request is served

#### Scenario: An unreachable database stops the container instead of half-serving

- GIVEN a database the container cannot reach
- WHEN the entrypoint exhausts its attempts
- THEN it reports the failure and exits non-zero

#### Scenario: Several instances booting together migrate once

- GIVEN the host starts more than one instance of the same deploy
- WHEN each runs the entrypoint
- THEN one applies the migrations and the others proceed without touching the schema

### Requirement: Cold-start work is baked into the image where it does not read the environment

Cache-building steps that read no environment — the route cache and the view cache — MUST be
built into the image, not run at boot: the host scales to zero after five minutes of
inactivity, so every one of them is paid again on each cold start. `config:cache` MUST stay at
boot, because it freezes every `env()` call and the build has neither `APP_KEY` nor database
credentials.

The deployment document MUST record what was measured, including the part that is not the
application's: container start to first response is ~4 s locally with the image present, of
which Laravel's own work is ~1 s, against the 60–90 s observed on a sleeping production
instance. Closing that gap needs a host-level decision (a warm instance), and the document
MUST say so rather than imply the application can fix it.

#### Scenario: The boot sequence holds only environment-dependent work

- GIVEN the container entrypoint
- WHEN it is read
- THEN it caches the configuration and migrates, and neither the route cache nor the view
  cache appears in it

### Requirement: Deferred host-specific hardening is recorded, not dropped

A deployment document MUST list every hardening item deferred because the hosting target
was undecided — at minimum: TLS termination, trusted-proxy configuration, a production
web-server configuration, production PHP settings, regeneration of the public storage
symlink, database backups, and Content-Security-Policy — each with the reason it was
deferred.

#### Scenario: Every deferred item is traceable

- GIVEN the change's proposal lists items as deferred pending a hosting decision
- WHEN the deployment document is read
- THEN each of those items appears in it with its reason
