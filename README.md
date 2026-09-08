# Campement

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker Compose](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![CI](https://github.com/NeitsabLc/campement/actions/workflows/ci.yaml/badge.svg)](https://github.com/NeitsabLc/campement/actions/workflows/ci.yaml)
[![Licence Apache 2.0](https://img.shields.io/badge/Licence-Apache%202.0-D22128?logo=apache&logoColor=white)](LICENSE)

## Description

Campement est une application web de gestion des camps accompagnés. Elle aide les équipes pilotes et les unités participantes à préparer les séjours, suivre les dossiers administratifs et organiser toute l’intendance, des menus aux commandes.

L’application repose sur Symfony, PostgreSQL, Liquibase, Nginx et Docker Compose.

## Fonctionnalités principales

- authentification, invitations, gestion des mots de passe, rôles et comptes actifs ;
- gestion de plusieurs séjours et affectation des gestionnaires ;
- suivi des unités, effectifs, participants, documents et présences ;
- gestion des situations particulières et des tâches associées ;
- création des menus, recettes et variantes alimentaires ;
- gestion des fournisseurs, denrées, conditionnements et mouvements de stock ;
- préparation des distributions selon les régimes, allergies et repas particuliers ;
- calcul des commandes et génération de listes de courses ;
- exports PDF, archives et envoi d’e-mails ;
- anonymisation et application des durées de conservation.

## Environnement local

### Prérequis

- Git ;
- Docker avec Docker Compose v2 ;
- GNU Make.

PHP, Composer, PostgreSQL, Liquibase et Nginx sont fournis par les conteneurs.

### Installation

```bash
git clone https://github.com/NeitsabLc/campement.git
cd campement
cp .env.example .env
cp app/.env.example app/.env
make install
```

Définir au préalable un `POSTGRES_PASSWORD` local dans `.env`. Ne jamais versionner les fichiers `.env`. Avec les valeurs par défaut, l’application est accessible sur <http://127.0.0.1:8080>.

Commandes courantes :

```bash
make up
make down
make ps
make logs
```

### Base de données

Liquibase est l’unique source de vérité du schéma ; Doctrine assure le mapping applicatif. Les migrations communes se trouvent dans `database/changelog/versioned/` et les données de développement dans `database/changelog/dev/`.

```bash
make db-validate
make db-status-dev
make db-sql-dev
make db-update-dev
make db-history
make db-shell
```

Un changeset déjà appliqué sur un environnement partagé ne doit jamais être modifié : toute évolution crée un nouveau changeset versionné. `make reset` détruit les conteneurs, les volumes et la base locale.

## Déploiement sur un serveur

La procédure générale consiste à :

1. préparer un serveur Linux avec Docker Compose, un nom de domaine, TLS et un espace persistant pour PostgreSQL, les documents et les sauvegardes ;
2. récupérer une version publiée et copier `.env.release.example` vers `.env.release` ;
3. injecter les secrets hors de Git et renseigner les images GHCR par digest ;
4. s’authentifier auprès de GHCR si les images sont privées, puis valider et vérifier les images ;
5. sauvegarder la base et les documents avant toute migration ;
6. contrôler puis appliquer les changesets Liquibase ;
7. démarrer les services et vérifier leur état, les journaux et le parcours de connexion ;
8. conserver la version précédente et une sauvegarde testée pour permettre un retour arrière.

```bash
make release-config
make release-verify
make release-pull
make release-db-status
make release-db-update
make release-up
make release-ps
```

Le proxy inverse, les certificats, les secrets, les sauvegardes et la supervision relèvent de la configuration du serveur et ne doivent pas être stockés dans le dépôt.

## Tests et CI

Les contrôles disponibles localement sont :

```bash
make doctrine-validate
make analyse-statique
make style
make test
make test-accessibility
make test-e2e
make backup-restore-test
```

`make test` recrée une base PostgreSQL isolée, applique les migrations et exécute PHPUnit. Les suites navigateur utilisent Playwright et Axe pour les parcours fonctionnels et l’accessibilité.

GitHub Actions exécute la qualité, les tests et un smoke test de la configuration de production sur les pull requests vers `main`. La CI contrôle notamment Docker Compose, Composer, Liquibase, Doctrine, PHPStan, le style, PHPUnit, les assets, l’accessibilité, les parcours E2E, les secrets et les vulnérabilités des images. Les releases publient des images GHCR signées, accompagnées d’un SBOM et d’une provenance.
