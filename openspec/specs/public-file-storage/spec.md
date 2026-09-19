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

### Requirement: Uploads to the public disk are restricted by type and size

Every form field that writes to the `public` disk MUST validate the uploaded file against an
explicit allow-list of raster image MIME types (`image/jpeg`, `image/png`, `image/webp`,
`image/gif`) and MUST enforce a maximum file size. A wildcard type constraint such as
`image/*` MUST NOT be used.

The allow-list MAY contain any raster format, which cannot carry executable script. What it
MUST NOT contain is a vector or markup-based format. Specifically it MUST exclude
`image/svg+xml`: files on the public disk are served from the application's own origin under
`/storage/`, so an SVG — which may carry executable script — would run with the site's origin
privileges, including access to an authenticated administrator's session. Filament's
`->image()` helper alone does not provide this exclusion: it sets
`acceptedFileTypes(['image/*'])`, which becomes the validation rule `mimetypes:image/*` and
matches `image/svg+xml`.

The maximum file size MUST be lower than the web server and PHP request-body limits, so that
an oversized upload is rejected as a form validation error rather than as a transport error.

#### Scenario: An SVG upload is rejected

- GIVEN an administrator is filling a form with a public-disk upload field
- WHEN they submit a file whose MIME type is `image/svg+xml`
- THEN the form returns a validation error on that field
- AND no file is written to the public disk

#### Scenario: A legitimate raster image is accepted

- GIVEN an administrator is filling a form with a public-disk upload field
- WHEN they submit a PNG within the size limit
- THEN the form submits without validation errors
- AND the file is written to the public disk under its configured directory

#### Scenario: An oversized upload is rejected as a validation error

- GIVEN an administrator is filling a form with a public-disk upload field
- WHEN they submit an allowed image type that exceeds the configured maximum size
- THEN the form returns a validation error on that field
- AND the rejection comes from application validation, not from the web server
