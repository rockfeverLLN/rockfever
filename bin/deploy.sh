#!/usr/bin/env bash
# Uploads the site to OVH over SFTP. Run by .github/workflows/deploy.yml after `composer install`.
#
# - Project files: only those changed since the commit recorded in .deployed-sha on the server
#   (full mirror when there is no record yet). OVH's SFTP doesn't keep file times, so lftp
#   can't detect changes by itself.
# - vendor/: only re-synced when composer.lock changed since the last deploy.
# - config/database.php is not in git and stays on the server untouched.
set -euo pipefail

SERVER=(lftp --env-password -u rockfevexj sftp://$FTP_HOST)
SETTINGS="set sftp:auto-confirm yes; set cmd:fail-exit yes; set mirror:parallel-transfer-count 8; set mirror:parallel-directories true;"
# Repo files that never go to the server
EXCLUDE=(':!.github' ':!.gitignore' ':!.editorconfig' ':!README.md')

remote_cat() {
    "${SERVER[@]}" -e "set sftp:auto-confirm yes; cat $1; quit" 2>/dev/null || true
}

quote() {
    printf '"%s"' "${1//\"/\\\"}"
}

COMMANDS="$SETTINGS"

DEPLOYED_SHA=$(remote_cat .deployed-sha | tr -d '[:space:]')
if [ -n "$DEPLOYED_SHA" ] && git cat-file -e "$DEPLOYED_SHA^{commit}" 2>/dev/null; then
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
    echo "No previous deploy recorded, uploading everything"
    COMMANDS+=" mirror --reverse --verbose --exclude '^\.git' --exclude '^\.editorconfig$' --exclude '^README\.md$' --exclude '^vendor/' --exclude '^tmp/' --exclude '^config/database\.php$' ./ ./;"
fi

# Built by bin/critical-css.php in deploy.yml and not in git, so not in the diff above: always upload them,
# over the previous build (after any "rm" above, for the deploy that took them out of git)
for built in views/critical.css www/css/site.css; do
    if [ -f "$built" ]; then
        echo "  upload $built (built)"
        COMMANDS+=" put $(quote "$built") -o $(quote "$built");"
    fi
done
# Built by earlier deploys and no longer used (merged into site.css)
COMMANDS+=" rm -f www/css/materialize.site.css;"

LOCK_HASH=$(sha256sum composer.lock | cut -c1-64)
if [ "$LOCK_HASH" != "$(remote_cat vendor/.lock-hash | tr -d '[:space:]')" ]; then
    echo "composer.lock changed, syncing vendor/"
    echo "$LOCK_HASH" > vendor/.lock-hash
    COMMANDS+=" mirror --reverse --delete vendor/ vendor/;"
else
    echo "composer.lock unchanged, skipping vendor/"
fi

# Record the deployed commit last, so a failed upload is retried in full next time
git rev-parse HEAD > .deployed-sha
COMMANDS+=" rm -r -f tmp/views tmp/routes tmp/di tmp/proxies; put .deployed-sha; quit"

if [ "${DRY_RUN:-}" = 1 ]; then
    echo "$COMMANDS" | tr ';' '\n'
else
    "${SERVER[@]}" -e "$COMMANDS"
fi
