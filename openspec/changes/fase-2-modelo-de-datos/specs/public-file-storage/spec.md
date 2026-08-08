# Public File Storage Specification

## Purpose

End-to-end serving of the Laravel `public` disk under `/storage/`, as groundwork for `Team.crest_path` uploads. The Filament upload UI itself is out of scope (Fase 3) — this spec covers only the storage plumbing.

## Requirements

### Requirement: Public disk is writable for crest uploads

The system MUST provide a writable `storage/app/public/crests/` directory, writable by the web server user (`www-data`), ready to receive files written via `Storage::disk('public')`.

#### Scenario: File can be written to the public disk

- GIVEN the `public` disk is configured
- WHEN a file is written under `crests/` via `Storage::disk('public')->put(...)`
- THEN the file exists on disk under `storage/app/public/crests/`

### Requirement: Public disk files are retrievable over HTTP at /storage/

The system MUST make files under the public disk retrievable via HTTP at `/storage/...`. The system MUST first attempt `php artisan storage:link` (the standard Laravel symlink). If that mechanism is unavailable or unreliable (e.g. NTFS bind-mount symlink failure), the system MUST fall back to an nginx `location /storage/ { alias ...; }` block committed in `docker/nginx/default.conf`, serving the same path directly without relying on the symlink.

#### Scenario: File written to public disk is served over HTTP

- GIVEN a file exists at `storage/app/public/crests/example.png`
- WHEN an HTTP GET is made to `/storage/crests/example.png`
- THEN the response returns the file content with a 200 status

#### Scenario: Symlink failure falls back to nginx alias

- GIVEN `php artisan storage:link` fails or produces a non-functional symlink (e.g. NTFS bind-mount restriction)
- WHEN a request is made to `/storage/...`
- THEN the nginx alias block serves the file directly, independent of the symlink's state

### Requirement: Serving mechanism is documented, not silently chosen

Whichever mechanism (symlink, nginx alias, or both) actually serves requests in the running environment MUST be recorded, so the divergence from a vanilla Laravel deploy is explicit rather than discovered later by a future non-Docker deploy.

#### Scenario: Mechanism recorded during apply

- GIVEN both `storage:link` and the nginx alias have been attempted/configured
- WHEN the apply phase completes
- THEN the project artifacts state which mechanism actually serves `/storage/...` in this environment
