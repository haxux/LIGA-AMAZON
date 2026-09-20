#!/usr/bin/env bash
#
# Runs the test suite against the managed MySQL (TiDB Cloud) rather than the
# in-memory SQLite that phpunit.xml forces.
#
# This is the check that decides whether TiDB can host this schema: the ten
# foreign keys in database/migrations carry business rules, and a
# MySQL-compatible engine is exactly where those semantics may diverge.
# DeleteStrategyTest is the one that matters.
#
# DANGER: RefreshDatabase drops and recreates every table on the target
# database on each run. .env.tidb must point DB_DATABASE at a throwaway
# database (liga_amazon_test), never at the one holding real league data.
#
# Usage:  ./scripts/test-tidb.sh [extra php artisan test arguments]
#   e.g.  ./scripts/test-tidb.sh --filter=DeleteStrategyTest

set -euo pipefail

if [ ! -f .env.tidb ]; then
    echo "Missing .env.tidb — copy .env.tidb.example and fill it from the TiDB Cloud panel." >&2
    exit 1
fi

# Sourced, never printed. Exported so they reach the PHP process, where
# Laravel's immutable dotenv leaves real environment variables untouched.
set -a
# shellcheck disable=SC1091
. ./.env.tidb
set +a

: "${DB_HOST:?DB_HOST is not set in .env.tidb}"
: "${DB_DATABASE:?DB_DATABASE is not set in .env.tidb}"

# Printed on purpose: a silent fallback to the local `db` container would look
# like a passing run while proving nothing about TiDB. No password is shown.
echo "Target: ${DB_USERNAME:-?}@${DB_HOST}:${DB_PORT:-4000}/${DB_DATABASE}"
echo

# vendor/bin/phpunit, NOT `artisan test`: artisan injects its own
# --configuration=phpunit.xml, PHPUnit refuses the flag twice, and the run
# silently falls back to phpunit.xml — which pins SQLite. The suite then passes
# without touching TiDB at all, which is the exact false negative this script
# exists to prevent.
# set -e is lifted around the run on purpose: a failing suite must not abort
# the script, or the verification below never executes and a failed run looks
# indistinguishable from one that never reached the server at all.
set +e
php vendor/bin/phpunit --configuration=phpunit.tidb.xml "$@"
status=$?
set -e

# Proof, not inference. The echo above only shows that the credentials were
# loaded; it cannot show which database PHPUnit actually used. RefreshDatabase
# leaves the migrated tables behind, so a table count of zero means the run
# never reached this server, whatever the test results said.
tables=$(php -r '
$pdo = new PDO(
    "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE"),
    getenv("DB_USERNAME"),
    getenv("DB_PASSWORD"),
    [PDO::MYSQL_ATTR_SSL_CA => getenv("MYSQL_ATTR_SSL_CA"), PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
echo count($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
')

echo
if [ "$tables" -eq 0 ]; then
    echo "FAILED VERIFICATION: ${DB_DATABASE} holds no tables, so the suite did NOT run against this server. Ignore the results above." >&2
    exit 1
fi
echo "Verified: the suite ran against ${DB_HOST}/${DB_DATABASE} (${tables} tables present)."
exit $status
