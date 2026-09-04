#!/bin/sh

registry_login_with_retry() {
    registry=${1:?Le registre doit etre renseigne}
    username=${2:?Le nom d utilisateur doit etre renseigne}
    token=${3:?Le jeton doit etre renseigne}
    tentative=1

    while [ "$tentative" -le 3 ]; do
        if printf '%s' "$token" | docker login "$registry" --username "$username" --password-stdin; then
            return 0
        fi

        if [ "$tentative" -eq 3 ]; then
            echo "Connexion a ${registry} impossible apres ${tentative} tentatives." >&2
            return 1
        fi

        sleep $((tentative * 5))
        tentative=$((tentative + 1))
    done
}

registry_resolve_digest_with_retry() (
    reference=${1:?La reference de l image doit etre renseignee}
    maximum=${2:-6}
    delai=${3:-10}
    tentative=1

    while [ "$tentative" -le "$maximum" ]; do
        digest=$(docker buildx imagetools inspect "$reference" 2>/dev/null \
            | awk '$1 == "Digest:" { print $2; exit }') || true
        if printf '%s\n' "$digest" | grep -Eq '^sha256:[0-9a-f]{64}$'; then
            printf '%s\n' "$digest"
            return 0
        fi

        if [ "$tentative" -lt "$maximum" ]; then
            sleep "$delai"
        fi
        tentative=$((tentative + 1))
    done

    echo "Digest indisponible apres ${maximum} tentatives : ${reference}" >&2
    return 1
)

release_image_names() {
    printf '%s\n' php nginx postgres liquibase backup
}
