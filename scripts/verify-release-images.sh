#!/bin/sh

set -eu

: "${CAMP_RELEASE_GIT_SHA:?CAMP_RELEASE_GIT_SHA doit etre renseigne}"
: "${CAMP_RELEASE_PHP_IMAGE:?CAMP_RELEASE_PHP_IMAGE doit etre renseignee}"
: "${CAMP_RELEASE_NGINX_IMAGE:?CAMP_RELEASE_NGINX_IMAGE doit etre renseignee}"
: "${CAMP_RELEASE_POSTGRES_IMAGE:?CAMP_RELEASE_POSTGRES_IMAGE doit etre renseignee}"
: "${CAMP_RELEASE_LIQUIBASE_IMAGE:?CAMP_RELEASE_LIQUIBASE_IMAGE doit etre renseignee}"
: "${CAMP_RELEASE_BACKUP_IMAGE:?CAMP_RELEASE_BACKUP_IMAGE doit etre renseignee}"

for commande in docker cosign; do
    if ! command -v "$commande" >/dev/null 2>&1; then
        echo "Commande requise absente : ${commande}" >&2
        exit 1
    fi
done

depot=NeitsabLc/campement
identite="https://github.com/${depot}/.github/workflows/publish-images.yaml@refs/heads/main"
emetteur="https://token.actions.githubusercontent.com"

if ! printf '%s\n' "$CAMP_RELEASE_GIT_SHA" | grep -Eq '^[0-9a-f]{40}$'; then
    echo "SHA Git de livraison invalide." >&2
    exit 1
fi

verifier_image() {
    nom=$1
    reference=$2
    prefixe="ghcr.io/neitsablc/campement-app-${nom}@sha256:"

    case "$reference" in
        "$prefixe"*) ;;
        *)
            echo "Reference inattendue pour ${nom} : ${reference}" >&2
            exit 1
            ;;
    esac

    digest=${reference#*@sha256:}
    if ! printf '%s\n' "$digest" | grep -Eq '^[0-9a-f]{64}$'; then
        echo "Digest SHA-256 invalide pour ${nom}." >&2
        exit 1
    fi

    echo "Verification de ${nom} (${reference})"
    docker buildx imagetools inspect "$reference" >/dev/null
    cosign verify "$reference" \
        --certificate-identity "$identite" \
        --certificate-oidc-issuer "$emetteur" \
        --certificate-github-workflow-repository "$depot" \
        --certificate-github-workflow-ref refs/heads/main \
        --certificate-github-workflow-sha "$CAMP_RELEASE_GIT_SHA" >/dev/null
}

verifier_image php "$CAMP_RELEASE_PHP_IMAGE"
verifier_image nginx "$CAMP_RELEASE_NGINX_IMAGE"
verifier_image postgres "$CAMP_RELEASE_POSTGRES_IMAGE"
verifier_image liquibase "$CAMP_RELEASE_LIQUIBASE_IMAGE"
verifier_image backup "$CAMP_RELEASE_BACKUP_IMAGE"

echo "Les cinq images et leurs signatures Sigstore sont valides."
