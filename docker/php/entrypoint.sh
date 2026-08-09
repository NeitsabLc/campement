#!/bin/sh
set -eu

# Le répertoire est un volume Docker en production comme en développement.
# À sa première création il appartient à root, alors que PHP-FPM traite les
# téléversements avec www-data.
install -d -o www-data -g www-data -m 0770 /var/www/app/var/documents_participants

# Symfony doit pouvoir alimenter ses caches et journaux depuis les workers
# PHP-FPM, exécutés avec www-data. Ces répertoires se trouvent sur le bind mount
# de l'application et peuvent avoir été recréés par root lors d'un déploiement.
install -d -o www-data -g www-data -m 0770 /var/www/app/var/cache /var/www/app/var/log
chown -R www-data:www-data /var/www/app/var/cache /var/www/app/var/log

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
