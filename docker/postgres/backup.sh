#!/bin/sh

set -eu
umask 077

repertoire=/backups
retention_minutes=10080
: "${BACKUP_AGE_RECIPIENT:?BACKUP_AGE_RECIPIENT doit contenir la clé publique age de sauvegarde}"

mkdir -p "$repertoire"

while true; do
    horodatage=$(date -u +%Y%m%dT%H%M%SZ)
    base_claire=$(mktemp "/tmp/campement-$horodatage.dump.XXXXXX")
    documents_clairs=$(mktemp "/tmp/documents-$horodatage.tar.gz.XXXXXX")
    base_temp="$repertoire/campement-$horodatage.dump.age.tmp"
    base_finale="$repertoire/campement-$horodatage.dump.age"
    documents_temp="$repertoire/documents-$horodatage.tar.gz.age.tmp"
    documents_finaux="$repertoire/documents-$horodatage.tar.gz.age"

    nettoyer() {
        rm -f "$base_claire" "$documents_clairs" "$base_temp" "$documents_temp"
    }
    trap nettoyer EXIT INT TERM

    pg_dump --format=custom --no-owner --no-acl \
        --host=database --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
        --file="$base_claire"
    tar -czf "$documents_clairs" -C /documents .

    age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" --output "$base_temp" "$base_claire"
    age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" --output "$documents_temp" "$documents_clairs"
    chmod 0600 "$base_temp" "$documents_temp"
    mv "$base_temp" "$base_finale"
    mv "$documents_temp" "$documents_finaux"
    rm -f "$base_claire" "$documents_clairs"
    trap - EXIT INT TERM

    find "$repertoire" -type f \
        \( -name 'campement-*.dump.age' -o -name 'documents-*.tar.gz.age' \) \
        -mmin "+$retention_minutes" -delete

    if [ "${BACKUP_ONCE:-0}" = "1" ]; then
        exit 0
    fi

    sleep 86400
done
