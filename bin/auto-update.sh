#!/usr/bin/env bash
# Installs package updates within a day of their release, each one tested before it goes live. Run every day by
# .github/workflows/update-dependencies.yml, on GitHub Actions: Composer and PHP run in their official Docker
# images, for the PHP version in PHP_VERSION (from .ovhconfig).
#
#   bin/auto-update.sh candidates   prints "package@version" for each update available, testing nothing. It changes
#                                   files, so run it in a throwaway checkout
#   bin/auto-update.sh apply        applies the updates and commits each group the tests pass: first every minor and
#                                   patch release together, then each new major version on its own. Writes
#                                   "package@version" of the ones that failed to failed.txt and their test output to
#                                   failed.md. Expects the workflow's test database and its site-php image
#
# The tests: PHP syntax, PHPStan (phpstan.neon: catches classes and methods a new version removed or changed), the
# pages answering (bin/smoke-test.sh) and their scripts working in Chrome (bin/browser-test.mjs).
set -euo pipefail
shopt -s inherit_errexit
# Same sort order for sort and join
export LC_ALL=C
cd "$(dirname "$0")/.."
: "${PHP_VERSION:?PHP_VERSION must be set, as in .ovhconfig}"

# The files updates change, the only ones committed or put back
UPDATED_FILES=(composer.json composer.lock www/js views src)

composer() {
    docker run --rm --user "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD:/app" -w /app \
        composer:2 "$@"
}
site() {
    docker run --rm --network host --user "$(id -u):$(id -g)" -v "$PWD:/app" -w /app site-php "$@"
}

# locked: "package version" of every package in composer.lock, sorted by name
locked() {
    jq -r '(.packages + ."packages-dev")[] | "\(.name) \(.version | ltrimstr("v"))"' composer.lock | sort -k1,1
}

# lock_changes BEFORE-FILE: Composer-like lines for what changed in composer.lock since `locked > BEFORE-FILE`
lock_changes() {
    join -a1 -a2 -e NONE -o 0,1.2,2.2 "$1" <(locked) | awk '
        $2 == "NONE" { print "- Installing " $1 " (" $3 ")"; next }
        $3 == "NONE" { print "- Removing " $1 " (" $2 ")"; next }
        $2 != $3     { print "- Upgrading " $1 " (" $2 " => " $3 ")" }'
}

# candidates_of CHANGES: "package@version" of each installed or upgraded package in Composer-like lines
candidates_of() {
    sed -nE 's/^- Upgrading ([^ ]+) \(.* => v?(.*)\)$/\1@\2/p; s/^- Installing ([^ ]+) \(v?(.*)\)$/\1@\2/p' <<<"$1"
}

# composer_majors: "package version" for each package of composer.json that has a new major version
composer_majors() {
    composer outdated --direct --major-only --locked --format=json --no-interaction \
        | jq -r '(.locked // .installed // [])[] | "\(.name) \(.latest | ltrimstr("v"))"'
}

# minor_updates [--no-install]: applies every minor and patch release, prints what changed
minor_updates() {
    local before
    before=$(mktemp)
    locked > "$before"
    # Only versions that run on the server's PHP, even if the Composer image has a newer one
    composer config platform.php "$PHP_VERSION"
    composer update "$@" --no-interaction --no-progress --quiet
    lock_changes "$before"
    bin/update-js.sh
}

# run_tests: everything must pass for an update to go live (&& rather than set -e, which is off inside an if)
run_tests() {
    # The site caches its compiled templates, routes and container: without this, a test would still show the
    # library URLs of the previous one (bin/deploy.sh empties the same folders on the server)
    rm -rf tmp/views tmp/routes tmp/di tmp/proxies \
        && composer install --no-interaction --no-progress --quiet \
        && echo "PHP syntax" && site sh -c "find src config www -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null" \
        && echo "PHPStan" && site vendor/bin/phpstan analyse --no-progress --memory-limit=1G \
        && { docker rm -f site > /dev/null 2>&1 || true; } \
        && docker run -d --name site --network host --user "$(id -u):$(id -g)" -v "$PWD:/app" -w /app site-php \
            php -S 127.0.0.1:8000 -t www bin/router.php > /dev/null \
        && sleep 2 \
        && bin/smoke-test.sh http://127.0.0.1:8000 \
        && echo "Browser" && node $(node -e WebSocket 2> /dev/null || echo --experimental-websocket) --no-warnings \
            bin/browser-test.mjs http://127.0.0.1:8000
}

# attempt TITLE CHANGES: commits the changes if the tests pass, else puts the files back and records them as failed
attempt() {
    local title=$1 changes=$2
    [ -n "$changes" ] || return 0
    echo "::group::$title"
    echo "$changes"
    if run_tests 2>&1 | tee test.log; then
        echo "::endgroup::"
        printf '%s\n\n%s\n' "$title" "$changes" > commit-message.txt
        git commit --quiet -F commit-message.txt -- "${UPDATED_FILES[@]}"
        echo "Installed: $title"
    else
        echo "::endgroup::"
        echo "::warning::Not installed, the tests failed: $title"
        fail "$title" "$changes" "$(tail -n 60 test.log; echo; echo 'Site errors:'; docker logs site 2>&1 | tail -n 20)"
        git checkout --quiet -- "${UPDATED_FILES[@]}"
        composer install --no-interaction --no-progress --quiet
    fi
}

# fail TITLE CHANGES OUTPUT
fail() {
    candidates_of "$2" >> failed.txt
    printf '### %s\n\n%s\n\n```\n%s\n```\n\n' "$1" "$2" "$3" >> failed.md
}

case ${1:-} in
    candidates)
        candidates_of "$(minor_updates --no-install)"
        composer_majors | tr ' ' @
        bin/update-js.sh --majors | tr ' ' @
        ;;
    apply)
        : > failed.txt
        : > failed.md
        changes=$(minor_updates)
        attempt "Update packages" "$changes"
        # Each major version alone, so one that breaks the site doesn't hold back the others
        composer_majors > majors.txt
        while read -r package version; do
            before=$(mktemp)
            locked > "$before"
            dev=$(jq -r --arg p "$package" '."require-dev"[$p] // empty' composer.json)
            if output=$(composer require ${dev:+--dev} "$package:^$(cut -d. -f1-2 <<<"$version")" \
                    --update-with-all-dependencies --no-interaction --no-progress 2>&1); then
                attempt "Upgrade $package to $version" "$(lock_changes "$before")"
            else
                fail "Upgrade $package to $version" "- Upgrading $package (? => $version)" "$output"
                git checkout --quiet -- "${UPDATED_FILES[@]}"
            fi
        done < majors.txt
        bin/update-js.sh --majors > majors.txt
        while read -r package version; do
            if changes=$(bin/update-js.sh "$package" 2>&1); then
                attempt "Upgrade $package to $version" "$changes"
            else
                fail "Upgrade $package to $version" "- Upgrading $package (? => $version)" "$changes"
                git checkout --quiet -- "${UPDATED_FILES[@]}"
            fi
        done < majors.txt
        docker rm -f site > /dev/null 2>&1 || true
        ;;
    *)
        echo "Usage: bin/auto-update.sh candidates|apply" >&2
        exit 1
        ;;
esac
