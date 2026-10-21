# Préparation PHP — 21 octobre 2026

Cible : PHP **8.4.27 FPM**, correctif des deux CVE-2026-109618 et CVE-2026-109619.
La préparation part de la dernière branche principale, après les corrections Dependabot.

## Image officielle vérifiée

- Référence : `php:8.4.27-fpm@sha256:64aff665308e23ba8804a6c43f75837e9bcdcc31735fc0f74af8899fe5304b07`.
- Manifest linux/amd64 : `sha256:d887786679bb47b5b5864c2c218ce649e407663174961a4d2a294114bff9020b`.
- Configuration : `sha256:d62c15151f776fbaa442df4c75a19ca1f2df991f89ce840c73686ec539c19ab2`.
- `PHP_VERSION=8.4.27` confirmé dans les métadonnées officielles ; cela ne remplace pas les tests applicatifs.

Dockerfile, Compose et exemples d'environnement sont alignés. L'ancien digest de
PHP 8.4.24 est retiré. Un contrôle exécuté dans la base de construction refuse un
interpréteur antérieur à 8.4.27 et toute autre branche PHP, même si un ancien `.env`
surcharge les nouvelles valeurs. Les tests de ce refus s'exécutent dans la CI.

```sh
python3 tests/infrastructure/test_php_security.py
```

## Validation et reprise séparées

1. Obtenir une CI complète réussie sur le commit final : construction PHP/Nginx,
   tests applicatifs et navigateur, audits et sauvegarde/restauration applicables.
2. Préparer une publication et un déploiement uniquement après autorisation.
   La publication d'une release déclenche automatiquement le déploiement en recette :
   ne pas fusionner une PR de version pour simplement préparer les images.
3. Aligner les valeurs explicites `PHP_VERSION` et `PHP_IMAGE_DIGEST` des environnements
   installés lors d'une future intervention autorisée. Les fichiers locaux `.env`
   ne sont pas modifiés par cette préparation.
4. Garder la surcharge de maintenance proxy pendant le déploiement corrigé et les
   contrôles des backends ; ne rouvrir que les domaines validés, sur autorisation.

Cette préparation ne lance aucun serveur ni conteneur du homelab et ne retire
aucune maintenance. Les anciennes images applicatives ne deviennent pas sûres
par la seule modification des sources.

Sources : [annonce PHP](https://www.php.net/),
[avis OOM](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w),
[avis état global](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp).
