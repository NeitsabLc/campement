#!/bin/sh

set -eu

case "${COMPOSE_PROJECT_NAME:-}" in
    *-ci-*|*-smoke-*) ;;
    *)
        echo "Le smoke test refuse un projet Compose non dédié (suffixe -ci- ou -smoke- requis)." >&2
        exit 2
        ;;
esac

compose() {
    docker compose -f compose.yaml -f compose.prod.yaml "$@"
}

: "${POSTGRES_USER:=campement}"
: "${POSTGRES_PASSWORD:?POSTGRES_PASSWORD doit etre renseigne pour le smoke test}"
export POSTGRES_USER POSTGRES_PASSWORD

assert_container_hardened() {
    service=$1
    expected_user=$2
    container_id=$(compose ps --quiet "$service")

    test -n "$container_id"
    test "$(docker inspect --format '{{.Config.User}}' "$container_id")" = "$expected_user"
    test "$(docker inspect --format '{{.HostConfig.ReadonlyRootfs}}' "$container_id")" = "true"
    docker inspect --format '{{json .HostConfig.CapDrop}}' "$container_id" | grep -q 'ALL'
    docker inspect --format '{{json .HostConfig.SecurityOpt}}' "$container_id" | grep -q 'no-new-privileges:true'
}

export BACKUP_DIR="${BACKUP_DIR:-/tmp/campement-production-smoke-${COMPOSE_PROJECT_NAME}}"
mkdir -p "$BACKUP_DIR"
chmod 0777 "$BACKUP_DIR"

compose config --quiet
compose build php nginx
compose up --detach --wait --wait-timeout 60 database
compose --profile tools run --rm \
    --env LIQUIBASE_COMMAND_USERNAME="$POSTGRES_USER" \
    --env LIQUIBASE_COMMAND_PASSWORD="$POSTGRES_PASSWORD" \
    liquibase update
compose exec --no-TTY database campement-harden-roles prepare
compose --profile tools run --rm liquibase update
compose exec --no-TTY database sh -ec '
    psql --username="$POSTGRES_MIGRATOR_USER" --dbname="$POSTGRES_DB" \
        --set=ON_ERROR_STOP=1 \
        --command="CREATE TABLE campement.ci_migrator_privilege_check (id integer); DROP TABLE campement.ci_migrator_privilege_check"
'
compose up --detach php nginx

curl --fail --silent --show-error --retry 30 --retry-delay 2 --retry-all-errors \
    --output /dev/null \
    "http://127.0.0.1:${NGINX_HOST_PORT:-8080}/login"

compose exec --no-TTY php php bin/console about --env=prod --no-debug
compose exec --no-TTY php php bin/console cache:warmup --env=prod --no-debug
compose exec --no-TTY php php bin/console dbal:run-sql \
    "SELECT current_database(), current_user, current_schema()"

assert_container_hardened php www-data
assert_container_hardened nginx nginx
assert_container_hardened database postgres

compose exec --no-TTY database sh -ec '
    app=$(psql --username="$POSTGRES_APP_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT
            has_schema_privilege(current_user, '"'"'campement'"'"', '"'"'USAGE'"'"'),
            NOT has_schema_privilege(current_user, '"'"'campement'"'"', '"'"'CREATE'"'"'),
            has_table_privilege(current_user, '"'"'campement.utilisateur'"'"', '"'"'SELECT'"'"')")
    migrator=$(psql --username="$POSTGRES_MIGRATOR_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT has_schema_privilege(current_user, '"'"'campement'"'"', '"'"'CREATE'"'"')")
    backup=$(psql --username="$POSTGRES_BACKUP_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT
            has_table_privilege(current_user, '"'"'campement.utilisateur'"'"', '"'"'SELECT'"'"'),
            NOT has_table_privilege(current_user, '"'"'campement.utilisateur'"'"', '"'"'INSERT'"'"')")
    test "$app" = "t|t|t"
    test "$migrator" = "t"
    test "$backup" = "t|t"
'

compose run --rm --env BACKUP_ONCE=1 backup
test -n "$(find "$BACKUP_DIR" -type f -name 'campement-*.dump' -size +0c -print -quit)"
test -n "$(find "$BACKUP_DIR" -type f -name 'documents-*.tar.gz' -size +0c -print -quit)"

compose exec --no-TTY php php bin/console app:sejours:anonymiser --env=prod --no-debug --no-interaction
compose exec --no-TTY php php bin/console app:donnees:purger --env=prod --no-debug --no-interaction
compose exec --no-TTY php php bin/console app:documents:reconcilier --env=prod --no-debug --no-interaction

compose exec --no-TTY database campement-harden-roles finalize

compose exec --no-TTY database sh -ec '
    case "$POSTGRES_USER" in *[!a-zA-Z0-9_]*) exit 2 ;; esac
    resultat=$(psql --username="$POSTGRES_HEALTHCHECK_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT NOT rolcanlogin FROM pg_roles WHERE rolname = '"'"'$POSTGRES_USER'"'"'")
    test "$resultat" = "t"
'

curl --fail --silent --show-error --retry 10 --retry-delay 2 --retry-all-errors \
    --output /dev/null \
    "http://127.0.0.1:${NGINX_HOST_PORT:-8080}/login"

docker save campement-php-production:"${APP_IMAGE_TAG:-local}" \
    --output /tmp/campement-php-production.tar
docker save campement-nginx-production:"${APP_IMAGE_TAG:-local}" \
    --output /tmp/campement-nginx-production.tar
