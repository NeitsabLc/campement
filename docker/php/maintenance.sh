#!/bin/sh
set -eu

while true; do
    php bin/console app:sejours:anonymiser --env=prod --no-debug
    php bin/console app:donnees:purger --env=prod --no-debug
    sleep 86400
done
