#!/usr/bin/env bash
# Updates the JavaScript libraries of the site, as Composer does for the PHP packages.
# Used by .github/workflows/update-dependencies.yml, which tests each change before it goes live.
#
# - www/js/jquery.min.js is a copy kept in git: it's replaced by the file of the npm package, checked against
#   the integrity hash npm publishes for it
# - The libraries loaded from jsDelivr (cdn.jsdelivr.net/npm/PACKAGE@VERSION/...) in the templates and
#   www/js/script.js: the version in their URLs is raised, once the new URLs are checked to exist
# Materialize isn't here: the project stopped in 2018 and the site uses a pre-release of it, there's nothing to update to.
#
# Usage:
#   bin/update-js.sh            newest version within the same major version of every library, prints one
#                               "- Upgrading package (old => new)" line per update, like Composer
#   bin/update-js.sh --majors   lists "package version" for the libraries that have a newer major version, changes nothing
#   bin/update-js.sh PACKAGE    newest version of that library, even a new major one
set -euo pipefail
cd "$(dirname "$0")/.."
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
# Where the jsDelivr URLs can be
CDN_FILES=(views src www/js/script.js)
CDN_URL='https://cdn\.jsdelivr\.net/npm/[a-z0-9.-]+@[0-9][0-9.]*/'

# metadata PACKAGE: the package's versions, from the npm registry
metadata() {
    curl -sfL --retry 3 -H 'Accept: application/vnd.npm.install-v1+json' "https://registry.npmjs.org/$1"
}

# newest METADATA [MAJOR]: the newest stable version, of that major version if given
newest() {
    jq -r --arg major "${2:-[0-9]+}" '[.versions | keys[] | select(test("^" + $major + "\\.[0-9]+\\.[0-9]+$"))]
        | sort_by(split(".") | map(tonumber)) | last // empty' <<<"$1"
}

# current PACKAGE: the version the site uses now
current() {
    if [ "$1" = jquery ]; then
        sed -n '1s/.*jQuery v\([0-9.]*\).*/\1/p' www/js/jquery.min.js
    else
        grep -rhoE "https://cdn\.jsdelivr\.net/npm/$1@[0-9][0-9.]*/" "${CDN_FILES[@]}" | head -1 | sed 's/.*@//; s|/$||'
    fi
}

# install PACKAGE METADATA VERSION
install() {
    if [ "$1" = jquery ]; then
        local tarball integrity
        tarball=$(jq -r --arg v "$3" '.versions[$v].dist.tarball' <<<"$2")
        integrity=$(jq -r --arg v "$3" '.versions[$v].dist.integrity' <<<"$2")
        curl -sfL --retry 3 -o "$TMP/package.tgz" "$tarball"
        if [ "sha512-$(openssl dgst -sha512 -binary "$TMP/package.tgz" | openssl base64 -A)" != "$integrity" ]; then
            echo "$1 $3 doesn't match its integrity hash on npm, not installed" >&2
            exit 1
        fi
        tar -xzOf "$TMP/package.tgz" package/dist/jquery.min.js > www/js/jquery.min.js
        return
    fi
    local url file
    # Every file of the package the site loads must exist in the new version before any URL changes
    for url in $(grep -rhoE "https://cdn\.jsdelivr\.net/npm/$1@[0-9][0-9.]*/[^\"' ?]*" "${CDN_FILES[@]}" | sort -u); do
        url="https://cdn.jsdelivr.net/npm/$1@$3/${url#*@*/}"
        curl -sfL --retry 3 -o /dev/null "$url" || { echo "$url doesn't exist, $1 $3 not installed" >&2; exit 1; }
    done
    for file in $(grep -rlE "https://cdn\.jsdelivr\.net/npm/$1@" "${CDN_FILES[@]}"); do
        sed -i.bak -E "s|https://cdn\.jsdelivr\.net/npm/$1@[0-9][0-9.]*/|https://cdn.jsdelivr.net/npm/$1@$3/|g" "$file"
        rm "$file.bak"
    done
}

# update PACKAGE [any]: installs the newest version of the same major version, or of any with "any"
update() {
    local current meta new
    current=$(current "$1")
    meta=$(metadata "$1")
    if [ "${2:-}" = any ]; then
        new=$(newest "$meta")
    else
        new=$(newest "$meta" "${current%%.*}")
    fi
    if [ -n "$new" ] && [ "$new" != "$current" ]; then
        install "$1" "$meta" "$new"
        echo "- Upgrading $1 ($current => $new)"
    fi
}

PACKAGES=(jquery $(grep -rhoE "$CDN_URL" "${CDN_FILES[@]}" | sed -E 's|.*/npm/||; s|@.*||' | sort -u))
case ${1:-} in
    '')
        for package in "${PACKAGES[@]}"; do
            update "$package"
        done
        ;;
    --majors)
        for package in "${PACKAGES[@]}"; do
            current=$(current "$package")
            latest=$(newest "$(metadata "$package")")
            if [ "${latest%%.*}" -gt "${current%%.*}" ]; then
                echo "$package $latest"
            fi
        done
        ;;
    *)
        update "$1" any
        ;;
esac
