#!/bin/sh
set -eu

# Le répertoire est un volume Docker en production comme en développement.
# À sa première création il appartient à root, alors que PHP-FPM traite les
# téléversements avec www-data.
install -d -o www-data -g www-data -m 0770 /var/www/app/var/documents_participants

# En développement, AssetMapper sert directement les sources et détecte leurs
# changements. Un ancien répertoire compilé prendrait le dessus et figerait les
# CSS/JS jusqu'à la prochaine compilation.
if [ "${APP_ENV:-dev}" = "prod" ]; then
    # Le répertoire compilé peut masquer les sources d'AssetMapper lors d'un
    # déploiement suivant. Il est entièrement généré et doit repartir de zéro.
    rm -rf public/assets
    php bin/console asset-map:compile --no-debug
else
    rm -rf public/assets
fi

exec "$@"
