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
