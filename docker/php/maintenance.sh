#!/bin/sh
set -eu

while true; do
    php bin/console app:sejours:anonymiser --env=prod --no-debug
    php bin/console app:donnees:purger --env=prod --no-debug
    php bin/console app:documents:reconcilier --supprimer --anciennete-heures=24 --env=prod --no-debug
    if [ "${MAINTENANCE_ONCE:-0}" = "1" ]; then
        exit 0
    fi
    sleep 86400
done
