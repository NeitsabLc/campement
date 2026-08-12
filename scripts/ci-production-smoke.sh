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
    if [ "${USE_RELEASE_IMAGES:-0}" = "1" ]; then
        docker compose -f compose.yaml -f compose.prod.yaml -f compose.release.yaml "$@"
    else
        docker compose -f compose.yaml -f compose.prod.yaml "$@"
    fi
}

: "${POSTGRES_USER:=campement}"
: "${POSTGRES_DB:=campement}"
: "${POSTGRES_PASSWORD:?POSTGRES_PASSWORD doit etre renseigne pour le smoke test}"
: "${POSTGRES_HEALTHCHECK_PASSWORD:?POSTGRES_HEALTHCHECK_PASSWORD doit etre renseigne pour le smoke test}"
export POSTGRES_USER POSTGRES_DB POSTGRES_PASSWORD POSTGRES_HEALTHCHECK_PASSWORD

repertoire_temporaire=$(mktemp -d)
export BACKUP_AGE_RECIPIENT=age1configuration-temporaire-remplacee-avant-sauvegarde

nettoyer() {
    statut=$?
    trap - EXIT INT TERM
    if [ "$statut" -ne 0 ]; then
        compose ps >&2 || true
        compose logs --no-color --tail=200 database php nginx maintenance backup >&2 || true
    fi
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf "$repertoire_temporaire"
    exit "$statut"
}
trap nettoyer EXIT INT TERM

assert_container_hardened() {
    service=$1
    expected_user=$2
    expected_memory=$3
    expected_nano_cpus=$4
    expected_pids=$5
    container_id=$(compose ps --quiet --all "$service")

    test -n "$container_id"
    test "$(docker inspect --format '{{.Config.User}}' "$container_id")" = "$expected_user"
    test "$(docker inspect --format '{{.HostConfig.ReadonlyRootfs}}' "$container_id")" = "true"
    docker inspect --format '{{json .HostConfig.CapDrop}}' "$container_id" | grep -q 'ALL'
    docker inspect --format '{{json .HostConfig.SecurityOpt}}' "$container_id" | grep -q 'no-new-privileges:true'
    test "$(docker inspect --format '{{.HostConfig.Memory}}' "$container_id")" = "$expected_memory"
    test "$(docker inspect --format '{{.HostConfig.NanoCpus}}' "$container_id")" = "$expected_nano_cpus"
    test "$(docker inspect --format '{{.HostConfig.PidsLimit}}' "$container_id")" = "$expected_pids"
}

export BACKUP_DIR="${BACKUP_DIR:-$repertoire_temporaire/backups}"
mkdir -p "$BACKUP_DIR"
chmod 0777 "$BACKUP_DIR"

compose config --quiet
if [ "${USE_RELEASE_IMAGES:-0}" = "1" ]; then
    compose --profile tools pull php nginx database liquibase backup
else
    compose build php nginx database liquibase backup
fi

fichier_identite="$repertoire_temporaire/identity.txt"
backup_image=${SMOKE_BACKUP_IMAGE:-campement-backup:${APP_IMAGE_TAG:-local}}
postgres_image=${SMOKE_POSTGRES_IMAGE:-campement-postgres-production:${APP_IMAGE_TAG:-local}}
docker run --rm --user root --entrypoint age-keygen \
    "$backup_image" >"$fichier_identite"
BACKUP_AGE_RECIPIENT=$(docker run --rm --user root \
    --volume "$fichier_identite:/run/identity.txt:ro" \
    --entrypoint age-keygen "$backup_image" \
    -y /run/identity.txt)
export BACKUP_AGE_RECIPIENT

compose up --detach --wait --wait-timeout 60 database
compose --profile tools run --rm \
    --env LIQUIBASE_COMMAND_USERNAME="$POSTGRES_USER" \
    --env LIQUIBASE_COMMAND_PASSWORD="$POSTGRES_PASSWORD" \
    liquibase update
compose exec --no-TTY database campement-harden-roles prepare
compose --profile tools run --rm liquibase update
compose exec --no-TTY database sh -ec '
    PGPASSWORD="$POSTGRES_MIGRATOR_PASSWORD" psql --host=127.0.0.1 \
        --username="$POSTGRES_MIGRATOR_USER" --dbname="$POSTGRES_DB" \
        --set=ON_ERROR_STOP=1 \
        --command="CREATE TABLE campement.ci_migrator_privilege_check (id integer); DROP TABLE campement.ci_migrator_privilege_check"
'
compose up --detach php nginx

curl --fail --silent --show-error --retry 30 --retry-delay 2 --retry-all-errors \
    --output /dev/null \
    "http://127.0.0.1:${NGINX_HOST_PORT:-8080}/login"
test "$(curl --silent --output /dev/null --write-out '%{http_code}' \
    --header 'Host: attaquant.example' \
    "http://127.0.0.1:${NGINX_HOST_PORT:-8080}/login")" = "400"
test "$(curl --silent --output /dev/null --write-out '%{http_code}' \
    --header 'Host: attaquant.example' \
    --header 'X-Forwarded-Host: localhost' \
    "http://127.0.0.1:${NGINX_HOST_PORT:-8080}/login")" = "400"

compose exec --no-TTY php php bin/console about --env=prod --no-debug
compose exec --no-TTY php php bin/console cache:warmup --env=prod --no-debug
compose exec --no-TTY php php bin/console dbal:run-sql \
    "SELECT current_database(), current_user, current_schema()"

compose --profile tools create maintenance backup liquibase
assert_container_hardened php www-data 536870912 1000000000 128
assert_container_hardened nginx nginx 134217728 500000000 64
assert_container_hardened database postgres 1073741824 2000000000 256
assert_container_hardened maintenance www-data 268435456 500000000 64
assert_container_hardened backup postgres 536870912 1000000000 128
assert_container_hardened liquibase liquibase 536870912 1000000000 128

compose exec --no-TTY database sh -ec '
    hba_file=$(PGPASSWORD="$POSTGRES_PASSWORD" psql --host=127.0.0.1 \
        --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --command="SHOW hba_file")
    if grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -q "[[:space:]]trust\([[:space:]]\|$\)"; then
        echo "Une règle trust subsiste dans pg_hba.conf." >&2
        exit 1
    fi
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -q "scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+campement[[:space:]]+campement_app[[:space:]]+172[.]30[.]0[.]0/16[[:space:]]+scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+campement[[:space:]]+campement_migrator[[:space:]]+172[.]30[.]0[.]0/16[[:space:]]+scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+campement[[:space:]]+campement_backup[[:space:]]+172[.]30[.]0[.]0/16[[:space:]]+scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+all[[:space:]]+campement_admin[[:space:]]+172[.]30[.]0[.]0/16[[:space:]]+scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+all[[:space:]]+all[[:space:]]+0[.]0[.]0[.]0/0[[:space:]]+reject"

    app=$(PGPASSWORD="$POSTGRES_APP_PASSWORD" psql --host=127.0.0.1 --username="$POSTGRES_APP_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT
            has_schema_privilege(current_user, '"'"'campement'"'"', '"'"'USAGE'"'"'),
            NOT has_schema_privilege(current_user, '"'"'campement'"'"', '"'"'CREATE'"'"'),
            has_table_privilege(current_user, '"'"'campement.utilisateur'"'"', '"'"'SELECT'"'"')")
    migrator=$(PGPASSWORD="$POSTGRES_MIGRATOR_PASSWORD" psql --host=127.0.0.1 --username="$POSTGRES_MIGRATOR_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT has_schema_privilege(current_user, '"'"'campement'"'"', '"'"'CREATE'"'"')")
    backup=$(PGPASSWORD="$POSTGRES_BACKUP_PASSWORD" psql --host=127.0.0.1 --username="$POSTGRES_BACKUP_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT
            has_table_privilege(current_user, '"'"'campement.utilisateur'"'"', '"'"'SELECT'"'"'),
            NOT has_table_privilege(current_user, '"'"'campement.utilisateur'"'"', '"'"'INSERT'"'"')")
    test "$app" = "t|t|t"
    test "$migrator" = "t"
    test "$backup" = "t|t"
'

compose exec --no-TTY --env PGPASSWORD="$POSTGRES_PASSWORD" database \
    psql --host=127.0.0.1 --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
    --set=ON_ERROR_STOP=1 \
    --command="CREATE ROLE role_interdit LOGIN PASSWORD 'mot-de-passe'"
if docker run --rm --network "${COMPOSE_PROJECT_NAME}_campement" \
    --entrypoint psql "$postgres_image" \
    "postgresql://role_interdit:mot-de-passe@database:5432/${POSTGRES_DB}" \
    --command='SELECT 1'; then
    echo "Le HBA accepte un rôle PostgreSQL non autorisé." >&2
    exit 1
fi

compose exec --no-TTY php sh -ec \
    'printf "document de contrôle\n" > var/documents_participants/ci-restauration.txt'
compose run --rm --env BACKUP_ONCE=1 backup
archive_base=$(find "$BACKUP_DIR" -type f -name 'campement-*.dump.age' -size +0c -print -quit)
archive_documents=$(find "$BACKUP_DIR" -type f -name 'documents-*.tar.gz.age' -size +0c -print -quit)
test -n "$archive_base"
test -n "$archive_documents"
if find "$BACKUP_DIR" -type f \
    \( -name '*.dump' -o -name '*.tar.gz' -o -name '*.tmp' \) -print -quit | grep -q .; then
    echo "Un fichier de sauvegarde en clair ou temporaire subsiste." >&2
    exit 1
fi

compose run --rm --no-deps \
    --env BACKUP_AGE_IDENTITY_FILE=/run/identity.txt \
    --env POSTGRES_USER="$POSTGRES_HEALTHCHECK_USER" \
    --env POSTGRES_DB="$POSTGRES_DB" \
    --env PGPASSWORD="$POSTGRES_HEALTHCHECK_PASSWORD" \
    --env RESTORE_DATABASE_NAME="${POSTGRES_DB}_production_restore_check" \
    --env EXPECTED_DOCUMENT=ci-restauration.txt \
    --volume "$fichier_identite:/run/identity.txt:ro" \
    --entrypoint /usr/local/bin/campement-verify-backup \
    backup "/backups/$(basename "$archive_base")" "/backups/$(basename "$archive_documents")"

compose run --rm --env MAINTENANCE_ONCE=1 maintenance

compose exec --no-TTY database campement-harden-roles finalize

compose exec --no-TTY database sh -ec '
    case "$POSTGRES_USER" in *[!a-zA-Z0-9_]*) exit 2 ;; esac
    resultat=$(PGPASSWORD="$POSTGRES_HEALTHCHECK_PASSWORD" psql --host=127.0.0.1 --username="$POSTGRES_HEALTHCHECK_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT NOT rolcanlogin FROM pg_roles WHERE rolname = '"'"'$POSTGRES_USER'"'"'")
    test "$resultat" = "t"
'

curl --fail --silent --show-error --retry 10 --retry-delay 2 --retry-all-errors \
    --output /dev/null \
    "http://127.0.0.1:${NGINX_HOST_PORT:-8080}/login"
