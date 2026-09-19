# Delta for public-file-storage

## ADDED Requirements

### Requirement: Uploads to the public disk are restricted by type and size

Every form field that writes to the `public` disk MUST validate the uploaded file against
an explicit allow-list of raster image MIME types (`image/jpeg`, `image/png`,
`image/webp`, `image/gif`) and MUST enforce a maximum file size. A wildcard type
constraint such as `image/*` MUST NOT be used.

The allow-list MAY contain any raster format, which cannot carry executable script. What
it MUST NOT contain is a vector or markup-based format.

The allow-list MUST exclude `image/svg+xml`. Files on the public disk are served from the
application's own origin under `/storage/`, so an SVG — which may carry executable script
— would run with the site's origin privileges, including access to an authenticated
administrator's session. Filament's `->image()` helper alone does not provide this
exclusion: it sets `acceptedFileTypes(['image/*'])`, which becomes the validation rule
`mimetypes:image/*` and matches `image/svg+xml`.

The maximum file size MUST be lower than the web server and PHP request-body limits, so
that an oversized upload is rejected as a form validation error rather than as a transport
error.

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
