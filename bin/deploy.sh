#!/usr/bin/env bash
# Uploads the site to OVH over SFTP. Run by .github/workflows/deploy.yml after `composer install`.
#
# - Project files: only those changed since the commit recorded in .deployed-sha on the server
#   (full mirror when there is no record yet). OVH's SFTP doesn't keep file times, so lftp
#   can't detect changes by itself.
# - vendor/: only re-synced when composer.lock changed since the last deploy.
# - DEPLOY_FULL=1 (deploy.yml's "full" option) uploads every file and vendor/ whatever changed.
# - config/database.php and storage/ (the gallery's photos) are not in git and stay on the server untouched.
# - The caches under tmp/ are emptied by renaming them to tmp/old-cache-*: one request, where removing them takes one
#   per file (30 to 40 seconds). `bin/deploy.sh clean` removes those, during the checks of the next deploy.
#
# The SFTP host comes from FTP_HOST (the repository's FTP_HOST secret), so it isn't in the public code.
set -euo pipefail

: "${FTP_HOST:?Set FTP_HOST to the SFTP host}"
SERVER=(lftp --env-password -u rockfevexj "sftp://$FTP_HOST")
SETTINGS="set sftp:auto-confirm yes; set cmd:fail-exit yes; set mirror:parallel-transfer-count 8; set mirror:parallel-directories true;"
# Repo files that never go to the server
EXCLUDE=(':!.github' ':!.gitignore' ':!.editorconfig' ':!README.md')

# A file that the last deploy recorded on the server, read with the others below
remote_cat() {
    cat "$REMOTE/$(basename "$1")" 2>/dev/null || true
}

quote() {
    printf '"%s"' "${1//\"/\\\"}"
}

if [ "${1:-}" = clean ]; then
    "${SERVER[@]}" -e "set sftp:auto-confirm yes; glob -a rm -r -f tmp/old-cache-* || echo 'No old caches to remove'; quit"
    exit
fi

# In one connection rather than one per file, which took 2 to 3 seconds each
REMOTE=$(mktemp -d)
"${SERVER[@]}" -e "set sftp:auto-confirm yes; lcd $REMOTE; get .deployed-sha; get vendor/.lock-hash; get bin/.cjxl-hash; quit" \
    > /dev/null 2>&1 || true

COMMANDS="$SETTINGS"

DEPLOYED_SHA=$(remote_cat .deployed-sha | tr -d '[:space:]')
if [ -z "${DEPLOY_FULL:-}" ] && [ -n "$DEPLOYED_SHA" ] && git cat-file -e "$DEPLOYED_SHA^{commit}" 2>/dev/null; then
    echo "Uploading changes since $DEPLOYED_SHA"
    while IFS= read -r -d '' status && IFS= read -r -d '' path; do
        case "$status" in
            D)
                echo "  delete $path"
                COMMANDS+=" rm -f $(quote "$path");"
                ;;
            *)
                echo "  upload $path"
                dir=$(dirname "$path")
                [ "$dir" != "." ] && COMMANDS+=" mkdir -p -f $(quote "$dir");"
                COMMANDS+=" put $(quote "$path") -o $(quote "$path");"
                ;;
        esac
    done < <(git -c core.quotePath=false diff -z --name-status --no-renames "$DEPLOYED_SHA" HEAD -- . "${EXCLUDE[@]}")
else
    echo "Full upload, or no previous deploy recorded: uploading everything"
    COMMANDS+=" mirror --reverse --verbose --exclude '^\.git' --exclude '^\.editorconfig$' --exclude '^README\.md$' --exclude '^vendor/' --exclude '^tmp/' --exclude '^storage/' --exclude '^config/database\.php$' ./ ./;"
fi

# Built by bin/critical-css.php in deploy.yml and not in git, so not in the diff above: always upload them,
# over the previous build (after any "rm" above, for the deploy that took them out of git)
for built in views/critical.css www/css/site.css; do
    if [ -f "$built" ]; then
        echo "  upload $built (built)"
        COMMANDS+=" put $(quote "$built") -o $(quote "$built");"
    fi
done
# The first-paint CSS of each page, without the files of pages that are gone
if [ -d views/critical ]; then
    echo "  upload views/critical/ (built)"
    COMMANDS+=" mkdir -p -f views/critical; mirror --reverse --delete views/critical/ views/critical/;"
fi
# Built by earlier deploys and no longer used (merged into site.css)
COMMANDS+=" rm -f www/css/materialize.site.css;"
# Written by the temporary error logging of the outage of 2026-11-14
COMMANDS+=" rm -f php-error.log;"

LOCK_HASH=$(sha256sum composer.lock | cut -c1-64)
if [ -n "${DEPLOY_FULL:-}" ] || [ "$LOCK_HASH" != "$(remote_cat vendor/.lock-hash | tr -d '[:space:]')" ]; then
    echo "composer.lock changed, syncing vendor/"
    echo "$LOCK_HASH" > vendor/.lock-hash
    COMMANDS+=" mirror --reverse --delete vendor/ vendor/;"
else
    echo "composer.lock unchanged, skipping vendor/"
fi

# The JPEG XL encoder that deploy.yml downloads (not in git): only when it's another version than the server's
if [ -f bin/cjxl ]; then
    CJXL_HASH=$(sha256sum bin/cjxl | cut -c1-64)
    if [ -n "${DEPLOY_FULL:-}" ] || [ "$CJXL_HASH" != "$(remote_cat bin/.cjxl-hash | tr -d '[:space:]')" ]; then
        echo "  upload bin/cjxl (downloaded)"
        echo "$CJXL_HASH" > bin/.cjxl-hash
        COMMANDS+=" put bin/cjxl -o bin/cjxl; chmod 755 bin/cjxl; put bin/.cjxl-hash -o bin/.cjxl-hash;"
    fi
fi

# Empty the caches once the new files are up
OLD_CACHE="tmp/old-cache-$(date +%s)"
COMMANDS+=" mkdir -f $OLD_CACHE;"
for cache in views routes di proxies; do
    COMMANDS+=" mv tmp/$cache $OLD_CACHE/$cache || echo 'No tmp/$cache cache';"
done

# Record the deployed commit last, so a failed upload is retried in full next time
git rev-parse HEAD > .deployed-sha
COMMANDS+=" put .deployed-sha; quit"

if [ "${DRY_RUN:-}" = 1 ]; then
    echo "$COMMANDS" | tr ';' '\n'
else
    "${SERVER[@]}" -e "$COMMANDS"
fi
