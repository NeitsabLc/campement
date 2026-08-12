#!/bin/sh

set -eu

: "${CAMP_RELEASE_PHP_IMAGE:?CAMP_RELEASE_PHP_IMAGE doit etre renseignee}"
: "${CAMP_RELEASE_NGINX_IMAGE:?CAMP_RELEASE_NGINX_IMAGE doit etre renseignee}"
: "${CAMP_RELEASE_POSTGRES_IMAGE:?CAMP_RELEASE_POSTGRES_IMAGE doit etre renseignee}"
: "${CAMP_RELEASE_LIQUIBASE_IMAGE:?CAMP_RELEASE_LIQUIBASE_IMAGE doit etre renseignee}"
: "${CAMP_RELEASE_BACKUP_IMAGE:?CAMP_RELEASE_BACKUP_IMAGE doit etre renseignee}"

for commande in docker cosign gh; do
    if ! command -v "$commande" >/dev/null 2>&1; then
        echo "Commande requise absente : ${commande}" >&2
        exit 1
    fi
done

depot=NeitsabLc/campement
workflow="${depot}/.github/workflows/publish-images.yaml"
identite="https://github.com/${workflow}@refs/heads/main"
emetteur=https://token.actions.githubusercontent.com

verifier_image() {
    nom=$1
    reference=$2
    prefixe="ghcr.io/neitsablc/campement-${nom}@sha256:"

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
    cosign verify \
        --certificate-identity "$identite" \
        --certificate-oidc-issuer "$emetteur" \
        "$reference" >/dev/null
    gh attestation verify "oci://${reference}" \
        --repo "$depot" \
        --signer-workflow "$workflow" \
        --source-ref refs/heads/main \
        --deny-self-hosted-runners \
        --bundle-from-oci >/dev/null
}

verifier_image php "$CAMP_RELEASE_PHP_IMAGE"
verifier_image nginx "$CAMP_RELEASE_NGINX_IMAGE"
verifier_image postgres "$CAMP_RELEASE_POSTGRES_IMAGE"
verifier_image liquibase "$CAMP_RELEASE_LIQUIBASE_IMAGE"
verifier_image backup "$CAMP_RELEASE_BACKUP_IMAGE"

echo "Les cinq images, signatures et attestations sont valides."
