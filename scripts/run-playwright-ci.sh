#!/bin/sh

set -eu

: "${APP_BASE_URL:?APP_BASE_URL doit être défini}"
: "${COMPOSE_PROJECT_NAME:?COMPOSE_PROJECT_NAME doit être défini}"
: "${PLAYWRIGHT_IMAGE:?PLAYWRIGHT_IMAGE doit être défini}"

if [ "$#" -eq 0 ]; then
    echo "Une commande npm doit être fournie." >&2
    exit 1
fi

docker_bin="$(realpath "$(command -v docker)")"
compose_plugin="$(
    docker info --format '{{range .ClientInfo.Plugins}}{{if eq .Name "compose"}}{{.Path}}{{end}}{{end}}'
)"
compose_plugin="$(realpath "$compose_plugin")"
docker_host="$(docker context inspect --format '{{.Endpoints.docker.Host}}')"

case "$docker_host" in
    unix://*) docker_socket="${docker_host#unix://}" ;;
    *)
        echo "Le contexte Docker doit utiliser une socket Unix." >&2
        exit 1
        ;;
esac

test -S "$docker_socket"
test -x "$docker_bin"
test -x "$compose_plugin"

socket_gid="$(stat -c '%g' "$docker_socket")"

exec docker run --rm --init --network host --ipc host \
    --user "$(id -u):$(id -g)" \
    --group-add "$socket_gid" \
    --env APP_BASE_URL \
    --env CI=1 \
    --env COMPOSE_PROJECT_NAME \
    --env DOCKER_HOST=unix:///var/run/docker.sock \
    --env HOME=/tmp/playwright \
    --env PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
    --volume "$docker_bin:/usr/local/bin/docker:ro" \
    --volume "$compose_plugin:/usr/local/lib/docker/cli-plugins/docker-compose:ro" \
    --volume "$docker_socket:/var/run/docker.sock" \
    --volume "$PWD:/work" \
    --workdir /work \
    "$PLAYWRIGHT_IMAGE" \
    npm run "$@"
