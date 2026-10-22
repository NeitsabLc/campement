# Architecture — Campement

> Organisation du projet, stack technique, commandes et gestion des données.
> Versions exactes : `app/composer.json`, `composer.lock`, Dockerfiles et fichiers Compose.

## 01 Vue d'ensemble

```text
Navigateur ──HTTPS──▶ Nginx ──FastCGI──▶ PHP-FPM (Symfony)
                                            │
                                            ├──▶ PostgreSQL 18   (schéma : Liquibase)
                                            ├──▶ Stockage documents participants (volume)
                                            └──▶ SMTP            (invitations, mots de passe)

Service maintenance : purges, anonymisation, réconciliation des documents (tâches planifiées)
Service backup      : sauvegardes chiffrées de la base et des documents
```

## 02 Stack

| Couche | Technologie |
|---|---|
| Langage | PHP 8.4 |
| Framework | Symfony 8.1, Doctrine ORM (mapping et requêtes) |
| Interface | Twig, AssetMapper, Symfony UX (Turbo, Stimulus), Dompdf pour les PDF |
| Base de données | PostgreSQL 18 |
| Migrations | Liquibase (seule source du schéma) |
| Tests | PHPUnit (unitaires et fonctionnels), Playwright + Axe (parcours, accessibilité) |
| Qualité | PHP-CS-Fixer (`make style`), PHPStan (`make analyse-statique`) |
| Exécution | Docker Compose : `php`, `nginx`, `database`, `liquibase`, `maintenance`, `backup` |

## 03 Commandes `make`

| Usage | Commandes |
|---|---|
| Inventaire | `make help` |
| Installation | `make install`, `make build`, `make composer-install` |
| Environnement | `make up`, `make down`, `make restart`, `make ps`, `make logs SERVICE=php`, `make shell` |
| Symfony | `make console ARGS="about"`, `make composer ARGS="…"`, `make cache-clear`, `make assets-compile` |
| Base | `make db-validate`, `make db-status`, `make db-sql`, `make db-update`, `make db-update-dev`, `make db-history`, `make db-shell` |
| Qualité | `make style`, `make style-fix`, `make analyse-statique`, `make doctrine-validate` |
| Tests | `make test`, `make test-db-reset`, `make test-accessibility`, `make test-e2e` |
| Livraison | `make prod-config`, `make release-config`, `make release-verify`, `make release-db-status` |
| Exploitation | `make backup-now`, `make backup-restore-test`, `make maintenance-now`, `make purge-data` |
| Destructif (accord requis) | `make reset`, `make prod-db-roles-finalize` |

## 04 Structure du projet

```text
Campement/
├─ app/                      application Symfony
│  ├─ src/
│  │  ├─ Controller/         orchestration HTTP, aucune règle métier
│  │  ├─ Service/            règles métier (ContexteSejour, PerimetreUtilisateur, calculs, PDF…)
│  │  ├─ Entity/ Repository/ Enum/   modèle Doctrine et requêtes
│  │  ├─ Security/           contrôle des comptes, CSP, modules de séjour
│  │  ├─ Command/            commandes de maintenance (purge, anonymisation, réconciliation)
│  │  └─ Twig/ Logging/ Doctrine/
│  ├─ templates/             gabarits Twig (dont components/)
│  ├─ assets/                CSS, contrôleurs Stimulus, images, polices
│  ├─ config/                configuration Symfony (packages/, routes)
│  └─ tests/                 PHPUnit : Unit/, Functional/
├─ database/changelog/       Liquibase : versioned/, dev/
├─ docker/                   images php, nginx, postgres, liquibase (+ scripts maintenance)
├─ tests/                    Playwright : accessibility/, e2e/, infrastructure/
├─ scripts/                  scripts CI, smoke test et vérification des images
├─ compose*.yaml             dev, production, livraison par images (release)
└─ Makefile                  point d'entrée des commandes
```

## 05 Configuration importante

- Modèles : `.env.example` (Compose) et `app/.env.example` (Symfony) ; valeurs réelles hors Git.
- Variables clés : `APP_ENV`, `APP_SECRET`, `APP_PUBLIC_URL`, `DEFAULT_URI`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`,
  `POSTGRES_*` (rôles séparés : application, migrateur, sauvegarde, healthcheck), `TRUSTED_PROXIES`, `TRUSTED_HOST_PATTERN`,
  `BACKUP_AGE_RECIPIENT` (clé publique de chiffrement des sauvegardes).
- Images : versions et digests épinglés (`*_VERSION`, `*_IMAGE_DIGEST`) ; la livraison utilise des images GHCR par digest.
- Sécurité : `app/config/packages/security.yaml` ; la vérification de version PHP est faite par `docker/php/verify-php-security.sh`.

## 06 Données

- Liquibase possède le schéma ; Doctrine ne génère jamais de migration (`doctrine-validate` utilise `--skip-sync`).
- `versioned/` : changesets `Vxxx__description.sql`, immuables une fois appliqués ; toute évolution = nouveau fichier.
- `dev/` : données de démonstration, contexte réservé aux environnements jetables.
- Le master `db.changelog-master.yaml` inclut `versioned/`, `repeatable/` (prévu, absent aujourd'hui) et `dev/` ; vérifier avec `make db-validate` et `make db-sql`.
- Sauvegarde restaurable obligatoire avant toute migration sur des données durables (`make backup-now`).
- Rôles PostgreSQL limités : l'application n'a pas les droits de modifier le schéma.

## 07 Performance et scalabilité

| Sujet | Règle |
|---|---|
| Cache | Pas de cache applicatif aujourd'hui. Si un calcul coûteux est réutilisé : pool Symfony `cache.app`, invalidé à l'écriture. Assets compilés et servis par Nginx. |
| Tâches longues | Ne pas les exécuter dans la requête : commande Symfony lancée par le service `maintenance`, ou message Messenger `async`. Aujourd'hui `MESSENGER_TRANSPORT_DSN=sync://` : prévoir un worker avant de passer en asynchrone. |
| Requêtes | Éviter les N+1 (jointures, `findPourSejour…`), index déclarés dans Liquibase. |
| PDF et exports | Générés à la demande ; surveiller la taille des listes avant d'ajouter un export lourd. |
| Modularité | Une fonctionnalité = contrôleur + service + gabarits dédiés ; modules activables par séjour. |
