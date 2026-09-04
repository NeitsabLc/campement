#!/bin/sh

set -eu

: "${GITHUB_ENV:?GITHUB_ENV doit etre renseigne par GitHub Actions}"

cp .env.example .env
cp app/.env.example app/.env

remplacer_variable() (
    fichier=${1:?Le fichier doit etre renseigne}
    variable=${2:?La variable doit etre renseignee}
    valeur=${3-}
    fichier_temporaire=$(mktemp "${fichier}.XXXXXX")

    sed "s/^${variable}=.*/${variable}=${valeur}/" "$fichier" > "$fichier_temporaire"
    mv "$fichier_temporaire" "$fichier"
)

app_secret_ci=$(openssl rand -hex 32)
echo "::add-mask::${app_secret_ci}"
remplacer_variable app/.env APP_ENV prod
remplacer_variable app/.env APP_SECRET "$app_secret_ci"
remplacer_variable .env APP_SECRET "$app_secret_ci"

for variable in \
    POSTGRES_PASSWORD \
    POSTGRES_APP_PASSWORD \
    POSTGRES_MIGRATOR_PASSWORD \
    POSTGRES_BACKUP_PASSWORD \
    POSTGRES_HEALTHCHECK_PASSWORD; do
    valeur=$(openssl rand -hex 24)
    echo "::add-mask::${valeur}"
    echo "${variable}=${valeur}" >> "$GITHUB_ENV"
done

echo "POSTGRES_HEALTHCHECK_USER=campement_admin" >> "$GITHUB_ENV"
