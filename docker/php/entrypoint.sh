#!/bin/sh
set -eu

# En développement, AssetMapper sert directement les sources et détecte leurs
# changements. Un ancien répertoire compilé prendrait le dessus et figerait les
# CSS/JS jusqu'à la prochaine compilation.
if [ "${APP_ENV:-dev}" = "prod" ]; then
    php bin/console asset-map:compile --no-debug
else
    rm -rf public/assets
fi

exec "$@"
