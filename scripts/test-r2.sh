#!/usr/bin/env bash
#
# Proves that the object storage bucket (Cloudflare R2) actually works as the
# 'uploads' disk, before anything is deployed.
#
# Three things can be wrong here and only one of them is visible from inside
# the application: the write credentials, the public read URL, and the
# agreement between the two. A bucket that accepts writes but serves nothing
# publicly looks perfectly healthy to Laravel and shows the visitor a broken
# image on every crest and every news cover. So this script does not ask
# Laravel whether the upload worked — it fetches the file back over plain
# HTTP, anonymously, the way a visitor's browser will.
#
# Usage:  ./scripts/test-r2.sh
#
# Run it inside the app container, where PHP matches production:
#   docker compose exec app ./scripts/test-r2.sh

set -euo pipefail

if [ ! -f .env.r2 ]; then
    echo "Missing .env.r2 — copy .env.r2.example and fill it from the Cloudflare panel." >&2
    exit 1
fi

# Sourced, never printed. Exported so they reach the PHP process, where
# Laravel's immutable dotenv leaves real environment variables untouched and
# these win over the development .env.
set -a
# shellcheck disable=SC1091
. ./.env.r2
set +a

# Checked one by one rather than with a loop so the message names the variable
# that is missing. An empty AWS_URL is the subtle one: the disk still works,
# Storage::url() silently falls back to the private API endpoint, and every
# image 404s for visitors while the panel looks fine.
: "${AWS_ENDPOINT:?AWS_ENDPOINT is not set in .env.r2}"
: "${AWS_BUCKET:?AWS_BUCKET is not set in .env.r2}"
: "${AWS_ACCESS_KEY_ID:?AWS_ACCESS_KEY_ID is not set in .env.r2}"
: "${AWS_SECRET_ACCESS_KEY:?AWS_SECRET_ACCESS_KEY is not set in .env.r2}"
: "${AWS_URL:?AWS_URL is not set in .env.r2 — without it images do not load for visitors}"

echo "Bucket:     ${AWS_BUCKET}"
echo "Endpoint:   ${AWS_ENDPOINT}"
echo "Public URL: ${AWS_URL}"
echo

# The check has to boot the framework: what is under test is the 'uploads'
# disk as the application resolves it, not a hand-rolled S3 client that could
# be configured correctly while config/filesystems.php is not. The file lives
# inside the project because the container sees the project, not /tmp.
check=storage/framework/cache/r2-check-$$.php
trap 'rm -f "$check"' EXIT

cat > "$check" <<'PHP'
<?php

require __DIR__ . '/../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

function fail(string $message): never
{
    fwrite(STDERR, "FAILED: {$message}\n");
    exit(1);
}

$disk = config('filesystems.uploads');

if ($disk !== 's3') {
    fail("config('filesystems.uploads') resolved to '{$disk}', not 's3'. UPLOADS_DISK did not reach the application.");
}

// A real 1x1 PNG, not a text file: the panel only ever writes images, and an
// object's content type is what decides whether a browser renders it or
// offers a download.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

// Written under crests/ because that is a directory the panel really uses:
// a bucket policy or lifecycle rule scoped to the wrong prefix would pass a
// test that wrote to the root and still lose every crest in production.
$path = 'crests/.r2-check-' . bin2hex(random_bytes(8)) . '.png';

// throw: true locally overrides the disk's 'throw' => false. Without it a
// rejected write returns false and this script would report a bucket problem
// as a mysterious empty read, hiding the S3 error that explains it.
$storage = Storage::build(array_merge(config('filesystems.disks.s3'), ['throw' => true]));

echo "1. Writing {$path} ... ";
$storage->put($path, $png, 'public');
echo "ok\n";

echo "2. Reading it back through the disk ... ";
$read = $storage->get($path);
if ($read !== $png) {
    fail('the bytes read back differ from the bytes written.');
}
echo "ok\n";

echo "3. Public URL ... ";
$url = $storage->url($path);
echo "{$url}\n";

if (! str_starts_with($url, rtrim(env('AWS_URL'), '/'))) {
    fail("the URL is not built from AWS_URL. Visitors would be sent to the private API endpoint, which rejects them.");
}

// The whole point of the script. Anonymous, no credentials, no SDK: exactly
// what an <img> tag does.
echo "4. Fetching that URL anonymously ... ";
$context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 15]]);
$fetched = @file_get_contents($url, false, $context);
$status = isset($http_response_header[0]) ? $http_response_header[0] : 'no response';

if ($fetched === false || ! str_contains($status, ' 200')) {
    fail("the object is not publicly readable ({$status}).\n"
        . "        The write succeeded, so the credentials are fine — it is the bucket's\n"
        . "        Public Development URL that is off, or AWS_URL points somewhere else.");
}

if ($fetched !== $png) {
    fail('the public URL served different bytes than were uploaded.');
}
echo "ok ({$status})\n";

echo "5. Deleting the test object ... ";
$storage->delete($path);
if ($storage->exists($path)) {
    fail("the object survived deletion. The token is missing delete permission, and removing a team in the panel would leave its crest behind.");
}
echo "ok\n";

echo "\nVerified: R2 accepts writes, serves them publicly, and honours deletes.\n";
PHP

php "$check"
