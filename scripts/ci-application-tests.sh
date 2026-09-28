#!/bin/sh

set -eu

cleanup() {
    status=$?
    if [ "$status" -ne 0 ]; then
        docker compose ps || true
        docker compose logs --no-color --tail=200 database php nginx || true
        docker compose exec --no-TTY php php -r '
            $classes = [];
            foreach (glob("var/log/prod-*.log") as $logFile) {
                foreach (file($logFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                    $entry = json_decode($line, true);
                    if (!is_array($entry) || ($entry["level"] ?? 0) < 400) {
                        continue;
                    }
                    $exception = $entry["context"]["exception"] ?? null;
                    while (is_array($exception)) {
                        if (isset($exception["class"])) {
                            $origine = isset($exception["file"])
                                ? basename($exception["file"])
                                : "origine inconnue";
                            $classes[$exception["class"]." (".$origine.")"] = true;
                        }
                        $exception = $exception["previous"] ?? null;
                    }
                }
            }
            foreach (array_keys($classes) as $class) {
                echo "Classe exception applicative : $class", PHP_EOL;
            }
        ' || true
    fi
    docker compose down --volumes --remove-orphans || true
    exit "$status"
}
trap cleanup EXIT INT TERM

CI_ENV_FILE="$CI_PROJECT_DIR/.ci.env" ./scripts/ci-prepare-env.sh
set -a
. "$CI_PROJECT_DIR/.ci.env"
set +a

npm ci --cache .npm --prefer-offline --no-audit --no-fund

set -- . \
    --file docker/php/Dockerfile \
    --target php-development \
    --load \
    --pull \
    --tag "$COMPOSE_PROJECT_NAME-php:latest"
if [ -n "${CI_REGISTRY_IMAGE:-}" ]; then
    set -- "$@" --cache-from "type=registry,ref=$CI_REGISTRY_IMAGE/cache/php"
fi
docker buildx build "$@"

mkdir -p .cache/composer
if [ -n "${GITHUB_ADVISORY_TOKEN:-}" ]; then
    COMPOSER_AUTH=$(printf '{"github-oauth":{"github.com":"%s"}}' "$GITHUB_ADVISORY_TOKEN")
    GITHUB_TOKEN=$GITHUB_ADVISORY_TOKEN
    export COMPOSER_AUTH GITHUB_TOKEN
fi

for tentative in 1 2 3; do
    if docker compose run --rm \
        --env COMPOSER_AUTH \
        --env COMPOSER_MAX_PARALLEL_HTTP \
        --env COMPOSER_CACHE_DIR=/composer-cache \
        --volume "$CI_PROJECT_DIR/.cache/composer:/composer-cache" \
        php composer install --no-interaction --prefer-dist --no-progress --no-scripts; then
        break
    fi
    [ "$tentative" -lt 3 ] || exit 1
    sleep "$((tentative * 5))"
done

docker compose up --detach database php nginx
published_port="$(docker compose port nginx 8080)"
app_port="${published_port##*:}"
APP_BASE_URL="http://127.0.0.1:$app_port"
export APP_BASE_URL

docker compose exec --no-TTY php composer validate --strict --no-check-publish
docker compose exec --no-TTY php composer audit --locked --no-interaction
docker compose exec --no-TTY -e GITHUB_TOKEN php php bin/console importmap:audit
docker compose --profile tools run --rm liquibase validate
docker compose exec --no-TTY php php bin/console doctrine:schema:validate --skip-sync

docker compose exec --no-TTY php php bin/console cache:clear --env=prod --no-debug
for tentative in 1 2 3; do
    docker compose exec --no-TTY php php bin/console importmap:install && break
    [ "$tentative" -lt 3 ] || exit 1
    sleep "$((tentative * 5))"
done
docker compose exec --no-TTY php php bin/console asset-map:compile --env=prod --no-debug

docker compose exec --no-TTY php php bin/console cache:warmup --env=dev
docker compose exec --no-TTY php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker compose exec --no-TTY php composer lint:php

base_test="$(docker compose exec --no-TTY database printenv POSTGRES_DB)_test"
docker compose exec --no-TTY database sh -c \
    'dropdb --username="$POSTGRES_USER" --force --if-exists "$1"' sh "$base_test"
docker compose exec --no-TTY database sh -c \
    'createdb --username="$POSTGRES_USER" --owner="$POSTGRES_USER" "$1"' sh "$base_test"
docker compose --profile tools run --rm \
    -e "LIQUIBASE_COMMAND_URL=jdbc:postgresql://database:5432/$base_test" \
    liquibase update --context-filter=dev

docker compose exec --no-TTY php php bin/phpunit
docker compose --profile tools run --rm liquibase update --context-filter=dev

docker run --rm --network host "$PLAYWRIGHT_IMAGE" \
    curl --fail --retry 30 --retry-delay 2 --retry-all-errors "$APP_BASE_URL/login"
./scripts/run-playwright-ci.sh test:accessibility
E2E_SKIP_CLEANUP=1
export E2E_SKIP_CLEANUP
./scripts/run-playwright-ci.sh test:e2e
