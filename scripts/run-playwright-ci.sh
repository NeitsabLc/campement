#!/bin/sh

set -eu

: "${APP_BASE_URL:?APP_BASE_URL doit être défini}"
: "${PLAYWRIGHT_IMAGE:?PLAYWRIGHT_IMAGE doit être défini}"

if [ "$#" -eq 0 ]; then
    echo "Une commande npm doit être fournie." >&2
    exit 1
fi

exec docker run --rm --init --network host --ipc host \
    --user "$(id -u):$(id -g)" \
    --env APP_BASE_URL \
    --env CI=1 \
    --env HOME=/tmp/playwright \
    --env PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
    --volume "$PWD:/work" \
    --workdir /work \
    "$PLAYWRIGHT_IMAGE" \
    npm run "$@"
