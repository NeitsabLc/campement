#!/bin/sh

set -eu

: "${CI_REGISTRY:?CI_REGISTRY doit etre renseigne}"
: "${CI_REGISTRY_IMAGE:?CI_REGISTRY_IMAGE doit etre renseigne}"
: "${CI_REGISTRY_USER:?CI_REGISTRY_USER doit etre renseigne}"
: "${CI_REGISTRY_PASSWORD:?CI_REGISTRY_PASSWORD doit etre renseigne}"
: "${CI_PROJECT_PATH:?CI_PROJECT_PATH doit etre renseigne}"
: "${CI_PROJECT_URL:?CI_PROJECT_URL doit etre renseigne}"
: "${CI_PIPELINE_ID:?CI_PIPELINE_ID doit etre renseigne}"
: "${COSIGN_IMAGE:?COSIGN_IMAGE doit etre renseigne}"
: "${RELEASE_VERSION:?RELEASE_VERSION doit etre renseigne}"
: "${RELEASE_GIT_SHA:?RELEASE_GIT_SHA doit etre renseigne}"

printf '%s\n' "$RELEASE_VERSION" | grep -Eq '^[0-9]+[.][0-9]+[.][0-9]+$' || {
    echo "Version invalide : $RELEASE_VERSION" >&2
    exit 2
}
printf '%s\n' "$RELEASE_GIT_SHA" | grep -Eq '^[0-9a-f]{40}$' || {
    echo "SHA Git invalide : $RELEASE_GIT_SHA" >&2
    exit 2
}

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
. "$script_dir/registry-helpers.sh"

printf '%s' "$CI_REGISTRY_PASSWORD" | docker login "$CI_REGISTRY" \
    --username "$CI_REGISTRY_USER" --password-stdin

tools_dir="$CI_PROJECT_DIR/.ci-tools"
mkdir -p "$tools_dir"
cosign_container=$(docker create "$COSIGN_IMAGE")
trap 'docker rm -f "$cosign_container" >/dev/null 2>&1 || true' EXIT INT TERM
docker cp "$cosign_container:/ko-app/cosign" "$tools_dir/cosign"
docker rm "$cosign_container" >/dev/null
trap - EXIT INT TERM
chmod 0755 "$tools_dir/cosign"
PATH="$tools_dir:$PATH"
export PATH

builder="release-$CI_PIPELINE_ID"
docker_context="$builder-context"
cleanup_builder() {
    docker buildx rm "$builder" >/dev/null 2>&1 || true
    docker context rm --force "$docker_context" >/dev/null 2>&1 || true
}
trap cleanup_builder EXIT INT TERM

# Buildx ne peut pas reutiliser directement les certificats TLS fournis par
# Docker-in-Docker. Un contexte les persiste avant la creation du builder.
docker context create "$docker_context" >/dev/null
docker buildx create --name "$builder" --driver docker-container --use "$docker_context" >/dev/null
docker buildx inspect --bootstrap >/dev/null

image_parameters() {
    case "$1" in
        php) printf '%s\n' 'docker/php/Dockerfile|php-production' ;;
        nginx) printf '%s\n' 'docker/php/Dockerfile|nginx-production' ;;
        postgres) printf '%s\n' 'docker/postgres/Dockerfile|postgres-runtime' ;;
        liquibase) printf '%s\n' 'docker/liquibase/Dockerfile|liquibase-runtime' ;;
        backup) printf '%s\n' 'docker/postgres/Dockerfile|backup-runtime' ;;
        *) echo "Image inconnue : $1" >&2; exit 2 ;;
    esac
}

candidate_tag="sha-$RELEASE_GIT_SHA"
if [ "${BUILD_CANDIDATE:-1}" = 1 ]; then
    : "${SIGSTORE_ID_TOKEN:?SIGSTORE_ID_TOKEN doit etre fourni par GitLab OIDC}"
    for image in $(release_image_names); do
        parameters=$(image_parameters "$image")
        dockerfile=${parameters%%|*}
        target=${parameters#*|}
        repository="$CI_REGISTRY_IMAGE/$image"
        docker buildx build . \
            --file "$dockerfile" \
            --target "$target" \
            --pull \
            --push \
            --tag "$repository:$candidate_tag" \
            --label "org.opencontainers.image.source=$CI_PROJECT_URL" \
            --label "org.opencontainers.image.revision=$RELEASE_GIT_SHA" \
            --attest type=sbom \
            --attest type=provenance,mode=max \
            --cache-from "type=registry,ref=$CI_REGISTRY_IMAGE/cache/$image" \
            --cache-to "type=registry,ref=$CI_REGISTRY_IMAGE/cache/$image,mode=max"
        digest=$(registry_resolve_digest_with_retry "$repository:$candidate_tag")
        cosign sign --yes \
            -a "gitlab_project_path=$CI_PROJECT_PATH" \
            -a "gitlab_commit_sha=$RELEASE_GIT_SHA" \
            "$repository@$digest"
    done
fi

release_env="$CI_PROJECT_DIR/.release.env"
printf 'CAMP_RELEASE_GIT_SHA=%s\n' "$RELEASE_GIT_SHA" >"$release_env"
for image in $(release_image_names); do
    repository="$CI_REGISTRY_IMAGE/$image"
    digest=$(registry_resolve_digest_with_retry "$repository:$candidate_tag")
    variable=$(printf '%s' "$image" | tr '[:lower:]' '[:upper:]')
    printf 'CAMP_RELEASE_%s_IMAGE=%s@%s\n' "$variable" "$repository" "$digest" >>"$release_env"
done

set -a
. "$release_env"
set +a
./scripts/verify-release-images.sh

CI_ENV_FILE="$CI_PROJECT_DIR/.ci.env" ./scripts/ci-prepare-env.sh
set -a
. "$CI_PROJECT_DIR/.ci.env"
. "$release_env"
set +a
export COMPOSE_PROJECT_NAME="campement-release-smoke-$CI_PIPELINE_ID"
export NGINX_HOST_PORT=18083
export POSTGRES_HOST_PORT=15437
export USE_RELEASE_IMAGES=1
export SMOKE_BACKUP_IMAGE="$CAMP_RELEASE_BACKUP_IMAGE"
export SMOKE_POSTGRES_IMAGE="$CAMP_RELEASE_POSTGRES_IMAGE"
./scripts/ci-production-smoke.sh

for image in $(release_image_names); do
    repository="$CI_REGISTRY_IMAGE/$image"
    candidate_digest=$(registry_resolve_digest_with_retry "$repository:$candidate_tag")
    current_digest=$(docker buildx imagetools inspect "$repository:$RELEASE_VERSION" 2>/dev/null \
        | awk '$1 == "Digest:" { print $2; exit }') || current_digest=
    if [ "$current_digest" = "$candidate_digest" ]; then
        printf '%s\n' "$repository:$RELEASE_VERSION deja promue vers $candidate_digest"
        continue
    fi
    docker buildx imagetools create --prefer-index=false \
        --tag "$repository:$RELEASE_VERSION" "$repository@$candidate_digest"
    promoted_digest=$(registry_resolve_digest_with_retry "$repository:$RELEASE_VERSION")
    [ "$promoted_digest" = "$candidate_digest" ] || {
        echo "Digest promu incorrect pour $repository:$RELEASE_VERSION" >&2
        exit 1
    }
done

cat >"$CI_PROJECT_DIR/deploy.env" <<EOF
DEPLOY_APPLICATION=campement
DEPLOY_GIT_SHA=$RELEASE_GIT_SHA
DEPLOY_VERSION=$RELEASE_VERSION
EOF
