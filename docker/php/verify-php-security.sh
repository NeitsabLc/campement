#!/bin/sh
set -eu

# Vérifier l'interpréteur réel, même si un ancien digest est fourni par .env.
version_id=$(php -r 'echo PHP_VERSION_ID;')
case "$version_id" in
    [0-9][0-9][0-9][0-9][0-9]) ;;
    *) printf '%s\n' "Erreur : version PHP illisible." >&2; exit 1 ;;
esac

if [ "$version_id" -lt 80427 ] || [ "$version_id" -ge 80500 ]; then
    printf '%s\n' "Erreur : PHP 8.4.27 minimum requis dans la branche 8.4 (CVE-2026-109618 / CVE-2026-109619)." >&2
    exit 1
fi

printf '%s\n' "Version PHP corrigée vérifiée : $version_id."

