#!/usr/bin/env bash
# Updates the JavaScript libraries of the site, as Composer does for the PHP packages.
# Used by .github/workflows/update-dependencies.yml, which tests each change before it goes live.
#
# The libraries are loaded from jsDelivr (cdn.jsdelivr.net/npm/PACKAGE@VERSION/...) in the templates and
# www/js/script.js: TinyMCE and its French translation, clipboard.js, Masonry. The version in their URLs is raised,
# once the new URLs are checked to exist. The site's own scripts (www/js) and base styles (www/css/base.css) use
# no library: there's no jQuery or Materialize left to update.
#
# Usage:
#   bin/update-js.sh            newest version within the same major version of every library, prints one
#                               "- Upgrading package (old => new)" line per update, like Composer
#   bin/update-js.sh --majors   lists "package version" for the libraries that have a newer major version, changes nothing
#   bin/update-js.sh PACKAGE    newest version of that library, even a new major one
set -euo pipefail
cd "$(dirname "$0")/.."
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
    grep -rhoE "https://cdn\.jsdelivr\.net/npm/$1@[0-9][0-9.]*/" "${CDN_FILES[@]}" | head -1 | sed 's/.*@//; s|/$||'
}

# install PACKAGE VERSION
install() {
    local url file
    # Every file of the package the site loads must exist in the new version before any URL changes
    for url in $(grep -rhoE "https://cdn\.jsdelivr\.net/npm/$1@[0-9][0-9.]*/[^\"' ?]*" "${CDN_FILES[@]}" | sort -u); do
        url="https://cdn.jsdelivr.net/npm/$1@$2/${url#*@*/}"
        curl -sfL --retry 3 -o /dev/null "$url" || { echo "$url doesn't exist, $1 $2 not installed" >&2; exit 1; }
    done
    for file in $(grep -rlE "https://cdn\.jsdelivr\.net/npm/$1@" "${CDN_FILES[@]}"); do
        sed -i.bak -E "s|https://cdn\.jsdelivr\.net/npm/$1@[0-9][0-9.]*/|https://cdn.jsdelivr.net/npm/$1@$2/|g" "$file"
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
        install "$1" "$new"
        echo "- Upgrading $1 ($current => $new)"
    fi
}

PACKAGES=($(grep -rhoE "$CDN_URL" "${CDN_FILES[@]}" | sed -E 's|.*/npm/||; s|@.*||' | sort -u))
case ${1:-} in
    '')
        for package in "${PACKAGES[@]}"; do
            update "$package"
        done
        ;;
    --majors)
        for package in "${PACKAGES[@]}"; do
            current=$(current "$package")
            # Two steps: set -e ignores a failed download inside a "$(...)" passed as an argument
            meta=$(metadata "$package")
            latest=$(newest "$meta")
            if [ "${latest%%.*}" -gt "${current%%.*}" ]; then
                echo "$package $latest"
            fi
        done
        ;;
    *)
        update "$1" any
        ;;
esac
