#!/usr/bin/env bash
# Loads the main public and admin pages of a running site and fails if any of them answers an error.
# Used by .github/workflows/deploy.yml before every upload, and by bin/auto-update.sh before package updates go live.
#
# Expects a database filled by `composer migrate && composer seed` (the admin login comes from RolesSeeder).
# Usage: bin/smoke-test.sh [base-url]   (default http://127.0.0.1:8000, as started by `composer serve`)
set -uo pipefail

BASE=${1:-http://127.0.0.1:8000}
COOKIES=$(mktemp)
BODY=$(mktemp)
trap 'rm -f "$COOKIES" "$BODY"' EXIT
FAILED=0
# PHP's own error messages: with display_errors on, as in PHP's Docker images, PHP's built-in server sends a fatal
# error with a 200 status, so the status alone doesn't show it
PHP_ERROR='(<b>)?(Fatal error|Parse error|Warning)(</b>)?: '

# check PATH [STATUS]: STATUS can list several, as "200|302"
check() {
    local path=$1 expected=${2:-200} status
    status=$(curl -s -o "$BODY" -w '%{http_code}' -b "$COOKIES" -c "$COOKIES" "$BASE$path")
    if [[ "|$expected|" != *"|$status|"* ]]; then
        echo "  FAIL $status $path"
        FAILED=1
    elif grep -qE "$PHP_ERROR" "$BODY"; then
        echo "  FAIL $status $path: $(grep -oE "$PHP_ERROR.*" "$BODY" | head -1 | sed -E 's/<[^>]+>//g' | cut -c1-160)"
        FAILED=1
    else
        echo "  ok   $status $path"
    fi
}

echo "Public pages"
for path in / /calendrier /calendrier.ics /api/events /sitemap.xml /contact /music /legal /login /photos; do
    check "$path"
done
# The next planned soirée, or home once they're all over
check /event '200|302'
check /calendrier/2000-01-01 404

echo "Admin pages"
curl -s -o /dev/null -b "$COOKIES" -c "$COOKIES" --data 'role=administrator&password=admin' "$BASE/login"
for path in /admin /admin/events /admin/events/past /admin/about /admin/about/gallery /admin/musics /admin/roles /admin/values /admin/photos; do
    check "$path"
done

exit $FAILED
