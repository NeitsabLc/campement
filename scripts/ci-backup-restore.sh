#!/bin/sh

set -eu

secret() {
    openssl rand -hex "$1"
}

export COMPOSE_PROJECT_NAME="campement-smoke-local-$$"
export APP_SECRET="$(secret 32)"
export POSTGRES_PASSWORD="$(secret 24)"
export POSTGRES_APP_PASSWORD="$(secret 24)"
export POSTGRES_MIGRATOR_PASSWORD="$(secret 24)"
export POSTGRES_BACKUP_PASSWORD="$(secret 24)"
export POSTGRES_HEALTHCHECK_PASSWORD="$(secret 24)"
export NGINX_HOST_PORT="${NGINX_HOST_PORT:-18083}"
export POSTGRES_HOST_PORT="${POSTGRES_HOST_PORT:-15437}"

exec ./scripts/ci-production-smoke.sh
