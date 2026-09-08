#!/bin/sh
set -eu

printf '{"event":"maintenance_started","timestamp":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
php bin/console app:sejours:anonymiser --env=prod --no-debug
php bin/console app:donnees:purger --env=prod --no-debug
php bin/console app:documents:reconcilier --supprimer --anciennete-heures=24 --env=prod --no-debug
printf '{"event":"maintenance_succeeded","timestamp":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
