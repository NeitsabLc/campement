#!/bin/sh

set -eu

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
# shellcheck source=registry-helpers.sh
. "$script_dir/registry-helpers.sh"

: "${CAMP_RELEASE_GIT_SHA:?CAMP_RELEASE_GIT_SHA doit etre renseigne}"

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

for image in $(release_image_names); do
    variable=$(printf '%s' "$image" | tr '[:lower:]' '[:upper:]')
    variable="CAMP_RELEASE_${variable}_IMAGE"
    eval "reference=\${${variable}:-}"
    if [ -z "$reference" ]; then
        echo "${variable} doit etre renseignee" >&2
        exit 1
    fi
    verifier_image "$image" "$reference"
done

echo "Les cinq images et leurs signatures Sigstore sont valides."
