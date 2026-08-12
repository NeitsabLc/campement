#!/bin/sh

set -eu

secret() {
    openssl rand -hex "$1"
}

export COMPOSE_PROJECT_NAME="campement-smoke-local-$$"
export APP_SECRET="$(secret 32)"
export POSTGRES_DB=campement
export POSTGRES_USER=campement
export POSTGRES_PASSWORD="$(secret 24)"
export POSTGRES_APP_USER=campement_app
export POSTGRES_APP_PASSWORD="$(secret 24)"
export POSTGRES_MIGRATOR_USER=campement_migrator
export POSTGRES_MIGRATOR_PASSWORD="$(secret 24)"
export POSTGRES_BACKUP_USER=campement_backup
export POSTGRES_BACKUP_PASSWORD="$(secret 24)"
export POSTGRES_HEALTHCHECK_USER=campement_admin
export POSTGRES_HEALTHCHECK_PASSWORD="$(secret 24)"
export NGINX_HOST_PORT="${NGINX_HOST_PORT:-18083}"
export POSTGRES_HOST_PORT="${POSTGRES_HOST_PORT:-15437}"

exec ./scripts/ci-production-smoke.sh
