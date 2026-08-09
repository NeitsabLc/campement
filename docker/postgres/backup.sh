#!/bin/sh
set -eu
umask 077

repertoire=/backups
retention_minutes=10080

mkdir -p "$repertoire"

while true; do
    horodatage=$(date -u +%Y%m%dT%H%M%SZ)
    base_temp="$repertoire/campement-$horodatage.dump.tmp"
    base_final="$repertoire/campement-$horodatage.dump"
    documents_temp="$repertoire/documents-$horodatage.tar.gz.tmp"
    documents_final="$repertoire/documents-$horodatage.tar.gz"

    pg_dump --format=custom --no-owner --no-acl \
        --host=database --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
        --file="$base_temp"
    mv "$base_temp" "$base_final"

    tar -czf "$documents_temp" -C /documents .
    mv "$documents_temp" "$documents_final"

    find "$repertoire" -type f \
        \( -name 'campement-*.dump' -o -name 'documents-*.tar.gz' \) \
        -mmin "+$retention_minutes" -delete

    if [ "${BACKUP_ONCE:-0}" = "1" ]; then
        exit 0
    fi

    sleep 86400
done
