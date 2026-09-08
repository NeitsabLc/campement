#!/bin/sh

set -eu

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
. "$script_dir/registry-helpers.sh"

: "${CAMP_RELEASE_GIT_SHA:?CAMP_RELEASE_GIT_SHA doit etre renseigne}"

for commande in docker cosign; do
    command -v "$commande" >/dev/null 2>&1 || {
        echo "Commande requise absente : $commande" >&2
        exit 1
    }
done

depot=neitsablc/campement
identite="^https://gitlab[.]com/${depot}//[.]gitlab-ci[.]yml@refs/tags/v[0-9]+[.][0-9]+[.][0-9]+$"
emetteur=https://gitlab.com

printf '%s\n' "$CAMP_RELEASE_GIT_SHA" | grep -Eq '^[0-9a-f]{40}$' || {
    echo "SHA Git de livraison invalide." >&2
    exit 1
}

for image in $(release_image_names); do
    variable=$(printf '%s' "$image" | tr '[:lower:]' '[:upper:]')
    variable="CAMP_RELEASE_${variable}_IMAGE"
    eval "reference=\${${variable}:-}"
    prefixe="registry.gitlab.com/neitsablc/campement/${image}@sha256:"
    case "$reference" in
        "$prefixe"*) ;;
        *) echo "Reference inattendue pour $image : $reference" >&2; exit 1 ;;
    esac
    digest=${reference#*@sha256:}
    printf '%s\n' "$digest" | grep -Eq '^[0-9a-f]{64}$' || {
        echo "Digest SHA-256 invalide pour $image." >&2
        exit 1
    }
    docker buildx imagetools inspect "$reference" >/dev/null
    cosign verify "$reference" \
        --certificate-identity-regexp "$identite" \
        --certificate-oidc-issuer "$emetteur" \
        -a "gitlab_project_path=$depot" \
        -a "gitlab_commit_sha=$CAMP_RELEASE_GIT_SHA" >/dev/null
 done

echo "Les cinq images et leurs signatures Sigstore GitLab sont valides."
