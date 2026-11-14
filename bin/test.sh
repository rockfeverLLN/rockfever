#!/usr/bin/env bash
# Every check a pull request must pass, Dependabot's package updates included: PHP syntax, PHPStan (phpstan.neon:
# catches classes and methods a new version removed or changed), the pages answering (bin/smoke-test.sh), signing up
# through MCP (bin/mcp-test.php) and the pages' scripts working in Chrome (bin/browser-test.mjs). Run by .github/workflows/test.yml on GitHub Actions: Composer and
# PHP run in their official Docker images.
#
# Expects the workflow's test database (filled by `phinx migrate` and `phinx seed:run`) and its site-php image.
set -euo pipefail
cd "$(dirname "$0")/.."

composer() {
    docker run --rm --user "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD:/app" -w /app \
        composer:2 "$@"
}
site() {
    docker run --rm --network host --user "$(id -u):$(id -g)" -v "$PWD:/app" -w /app site-php "$@"
}

composer install --no-interaction --no-progress --quiet
echo "PHP syntax"
site sh -c "find src config www -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null"
echo "PHPStan"
site vendor/bin/phpstan analyse --no-progress --memory-limit=1G
echo "No paths from the current folder"
bin/check-paths.sh
echo "Album cookies"
site php bin/photo-cookie-test.php

docker rm -f site > /dev/null 2>&1 || true
docker run -d --name site --network host --user "$(id -u):$(id -g)" -v "$PWD:/app" -w /app site-php \
    php -S 127.0.0.1:8000 -t www bin/router.php > /dev/null
trap 'echo; echo "Site errors:"; docker logs site 2>&1 | tail -n 20; docker rm -f site > /dev/null' EXIT
# Until the server answers, rather than a fixed time a slow runner can exceed
for i in $(seq 30); do curl -s -o /dev/null http://127.0.0.1:8000/ && break; sleep 1; done

bin/smoke-test.sh http://127.0.0.1:8000
echo "MCP sign-up"
site php bin/mcp-test.php http://127.0.0.1:8000
echo "Browser"
node $(node -e WebSocket 2> /dev/null || echo --experimental-websocket) --no-warnings \
    bin/browser-test.mjs http://127.0.0.1:8000
