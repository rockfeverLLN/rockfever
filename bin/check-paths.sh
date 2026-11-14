#!/usr/bin/env bash
# Fails if the site's code names one of its folders by a path from the current folder ('tmp/views',
# "vendor/autoload.php"): on 2026-11-14 OVH moved the hosting and PHP there couldn't resolve its current folder, so
# every such path broke and every page answered 500. Build them from __DIR__ instead: dirname(__DIR__, 2) . '/tmp'.
# Run by bin/test.sh on every pull request. Comments are skipped, and so are lines ending in "// made absolute by …"
# (a path from the site's folder that the code prefixes). The scripts in bin/ aren't checked: they run from the site's folder.
set -euo pipefail
cd "$(dirname "$0")/.."

found=$(grep -rnE --include='*.php' "['\"](\./)?(tmp|storage|www|views|config|vendor|src)/" src config www \
    | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|/\*)' | grep -v '// made absolute by ' || true)
if [ -n "$found" ]; then
    echo "$found"
    echo "These paths depend on the current folder: build them from __DIR__ (see bin/check-paths.sh)"
    exit 1
fi
