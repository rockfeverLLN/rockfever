#!/usr/bin/env bash
# Loads the main pages of the live site and fails if one of them doesn't answer 200 with a whole page.
# Run by .github/workflows/deploy.yml after every upload and by .github/workflows/uptime.yml every 10 minutes.
# A browser's User-Agent: OVH refuses curl's. Each page gets 3 tries, so a slow second isn't an outage.
# Usage: bin/check-live.sh [base-url]   (default https://rockfever.be)
set -uo pipefail

BASE=${1:-https://rockfever.be}
UA='Mozilla/5.0 (rockfever site check)'
BODY=$(mktemp)
trap 'rm -f "$BODY"' EXIT
FAILED=0

for path in / /calendrier /event /photos /contact /login /api/events /calendrier.ics; do
    for try in 1 2 3; do
        status=$(curl -sS -L --max-time 30 -o "$BODY" -w '%{http_code}' -A "$UA" "$BASE$path" 2> /dev/null)
        # The pages end with </html>; a PHP error halfway through cuts them short. The feeds have their own ends
        if [ "$status" = 200 ] && { [[ $path == /api/* || $path == *.ics ]] || grep -q '</html>' "$BODY"; } \
            && ! grep -qE '(Fatal error|Parse error|Uncaught )' "$BODY"; then
            echo "  ok   $path"
            continue 2
        fi
        [ $try -lt 3 ] && sleep 10
    done
    echo "  FAIL $status $path"
    FAILED=1
done
exit $FAILED
