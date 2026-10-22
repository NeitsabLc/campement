# Architecture — Campement

## Organisation

- Application Symfony dans `app/` : contrôleurs, formulaires, services, entités, repositories, sécurité et gabarits.
- PostgreSQL 18 : schéma défini par les changelogs Liquibase de `database/changelog/`.
- Doctrine : mapping et requêtes ; aucune génération automatique du schéma.
- Assets : Twig, AssetMapper et composants Symfony UX ; respecter l’organisation existante.
- Docker Compose fournit PHP, Nginx, PostgreSQL et les outils Liquibase.
- PHPUnit couvre le métier ; Playwright/Axe couvre les parcours et l’accessibilité.

## Données

Chaque changement de schéma est un nouveau changeset. Les fichiers déjà appliqués restent immuables.
Le master référence les migrations et les contextes de développement restent réservés aux environnements jetables.
La CI et les cibles du Makefile sont la référence des commandes exactes.

## Sources

- `README.md`, `CONTRIBUTING.md`, `GITHUB_SETUP.md` et `Makefile`.
- Versions effectives : `app/composer.json`, verrou Composer, images Docker et fichiers Compose.
