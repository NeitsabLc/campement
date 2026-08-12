#!/bin/sh

set -eu
umask 077

archive_base=${1:?Indiquez le chemin du fichier .dump.age à restaurer}
archive_documents=${2:?Indiquez le chemin du fichier .tar.gz.age à restaurer}
: "${BACKUP_AGE_IDENTITY_FILE:?BACKUP_AGE_IDENTITY_FILE doit désigner la clé privée age}"
: "${POSTGRES_HOST:=database}"
: "${POSTGRES_USER:?POSTGRES_USER doit contenir le rôle administrateur de restauration}"
: "${POSTGRES_DB:?POSTGRES_DB doit contenir le nom de la base source}"
: "${RESTORE_DATABASE_NAME:=${POSTGRES_DB}_restore_check}"

sauvegarde_claire=$(mktemp /tmp/campement-restore.dump.XXXXXX)
documents_clairs=$(mktemp /tmp/campement-documents.tar.gz.XXXXXX)
documents_restaures=$(mktemp -d /tmp/campement-documents.XXXXXX)

nettoyer() {
    rm -f "$sauvegarde_claire" "$documents_clairs"
    rm -rf "$documents_restaures"
    dropdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
        --if-exists --force "$RESTORE_DATABASE_NAME" >/dev/null 2>&1 || true
}
trap nettoyer EXIT INT TERM

age --decrypt --identity "$BACKUP_AGE_IDENTITY_FILE" --output "$sauvegarde_claire" "$archive_base"
age --decrypt --identity "$BACKUP_AGE_IDENTITY_FILE" --output "$documents_clairs" "$archive_documents"
tar -xzf "$documents_clairs" -C "$documents_restaures"

dropdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" --if-exists --force "$RESTORE_DATABASE_NAME"
createdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" --owner="$POSTGRES_USER" "$RESTORE_DATABASE_NAME"
pg_restore --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$RESTORE_DATABASE_NAME" --exit-on-error --no-owner --no-acl \
    "$sauvegarde_claire"

source_count=$(psql --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB" --tuples-only --no-align \
    --command='SELECT COUNT(*) FROM campement.utilisateur')
restore_count=$(psql --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$RESTORE_DATABASE_NAME" --tuples-only --no-align \
    --command='SELECT COUNT(*) FROM campement.utilisateur')
test "$restore_count" = "$source_count"

if [ -n "${EXPECTED_DOCUMENT:-}" ]; then
    test -f "/documents/$EXPECTED_DOCUMENT"
    test -f "$documents_restaures/$EXPECTED_DOCUMENT"
    cmp "/documents/$EXPECTED_DOCUMENT" "$documents_restaures/$EXPECTED_DOCUMENT"
fi

echo "Restauration vérifiée dans $RESTORE_DATABASE_NAME ($restore_count utilisateurs)."
