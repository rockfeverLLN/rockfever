#!/usr/bin/env bash
# Loads the main public and admin pages of a running site and fails if any of them answers an error.
# Used by .github/workflows/update-dependencies.yml before package updates go live.
#
# Expects a database filled by `composer migrate && composer seed` (the admin login comes from RolesSeeder).
# Usage: bin/smoke-test.sh [base-url]   (default http://127.0.0.1:8000, as started by `composer serve`)
set -uo pipefail

BASE=${1:-http://127.0.0.1:8000}
COOKIES=$(mktemp)
trap 'rm -f "$COOKIES"' EXIT
FAILED=0

check() {
    local path=$1 expected=${2:-200} status
    status=$(curl -s -o /dev/null -w '%{http_code}' -b "$COOKIES" -c "$COOKIES" "$BASE$path")
    if [ "$status" = "$expected" ]; then
        echo "  ok   $status $path"
    else
        echo "  FAIL $status $path"
        FAILED=1
    fi
}

echo "Public pages"
for path in / /contact /music /legal /login /photos; do
    check "$path"
done
# The seeded events are all in the past, and without an upcoming event this page sends you home
check /event 302

echo "Admin pages"
curl -s -o /dev/null -b "$COOKIES" -c "$COOKIES" --data 'role=administrator&password=admin' "$BASE/login"
for path in /admin /admin/events /admin/about /admin/about/gallery /admin/musics /admin/roles /admin/values /admin/photos; do
    check "$path"
done

exit $FAILED
