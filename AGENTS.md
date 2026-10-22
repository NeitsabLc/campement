# Campement

Gestion des camps accompagnés : séjours, unités, participants, dossiers et intendance.

## Stack

- PHP 8.4, Symfony 8.1, Doctrine et PostgreSQL 18 ; vérifier les versions exactes dans les manifestes.
- Twig, Symfony UX/AssetMapper et Docker Compose ; npm pour Playwright/Axe.
- Liquibase est l’unique source de vérité du schéma ; PHPUnit pour PHP.

## Commandes depuis la racine

- Inventaire des commandes : `make help`.
- Installation locale : préparer `.env` et `app/.env` depuis les modèles, puis `make install`.
- Démarrer et inspecter : `make up`, `make ps` ; dépendances PHP : `make composer-install`.
- Schéma : `make db-validate` ; SQL prévu sans application : `make db-sql` ; état : `make db-status`.
- Mapping, style et analyse : `make doctrine-validate`, `make style`, `make analyse-statique`.
- Tests PHP : `make test` ; cette cible recrée la base de tests dédiée.
- Tests navigateur : `npm ci`, puis `make test-accessibility` ou `make test-e2e`.
- Conteneurs et livraison : `make prod-config` ; smoke test selon `GITHUB_SETUP.md`.
- Vérification du diff : `git diff --check`.

## Architecture et conventions

- `app/src/Controller/` : orchestration HTTP ; placer les règles métier dans `app/src/Service/` et les entités.
- `app/templates/` et `app/assets/` : interface ; `app/tests/` et `tests/` : tests PHP/navigateur.
- `database/changelog/` : schéma ; `docker/`, `scripts/` et les fichiers Compose : exécution.
- Suivre `CONTRIBUTING.md` et `GITHUB_SETUP.md` pour branches, PR, releases et contrôles.
- Conserver les textes d’interface et les descriptions de commits en français, avec les préfixes Conventional Commits.

## Pièges connus

- Ne jamais modifier un changeset Liquibase déjà appliqué ; ajouter un nouveau fichier et le référencer dans le master.
- Ne jamais appliquer `doctrine:schema:update --force` ni générer une seconde chaîne de migrations Doctrine.
- La validation Doctrine utilise `--skip-sync` ; les defaults/index Liquibase peuvent produire des écarts diagnostiques volontaires.
- `make test` recrée la base de tests ; les tests navigateur peuvent appliquer des données de développement.
- Vérifier le projet Compose et la base visée avant ces tests ; utiliser seulement une installation locale jetable.
- `make reset`, suppressions de volumes, purges et restaurations nécessitent un accord explicite.
- Toute lecture et écriture métier doit respecter le séjour courant et les habilitations côté serveur.
- `ContexteSejour` et `PerimetreUtilisateur` centralisent ces contrôles.
- Les données de développement restent dans `database/changelog/dev/`.
- Les documents participants font partie du périmètre de sauvegarde, avec la base.

## Collaboration et sécurité

- Répondre en français ; respecter les conventions du dépôt et les composants existants.
- Préserver les modifications locales et les données ; lire `git status --short` avant toute modification.
- Utiliser une branche ciblée et les Conventional Commits ; ne pas pousser ni fusionner sans demande explicite.
- Ne jamais écrire de secret dans les consignes, la documentation, les tests ou Git.
- Les secrets sont injectés hors Git ; consulter les modèles `.env.example` et les procédures, sans ouvrir les valeurs privées.
- Exécuter les contrôles adaptés au changement et indiquer leurs résultats et limites.
- Demander un accord explicite avant une action destructive, une restauration ou un changement de production.

## Mémoire à maintenir

- `AGENTS.md` est la source unique des consignes ; `CLAUDE.md` contient seulement `@AGENTS.md`.
- Garder ici les commandes et invariants utiles à chaque session.
- Mettre les détails dans `docs/context/` et les décisions durables dans `decisions.md`.
- Dater les observations dans `etat.md` ; distinguer fait vérifié, préférence, proposition et travail restant.
- Actualiser la rubrique concernée après une tâche ; remplacer les états périmés au lieu d'empiler les comptes rendus.
- Lire les documents liés selon la tâche ; ne pas charger tout l'historique à chaque session.


## Documentation à lire selon la tâche

- [Index du contexte](docs/context/README.md) ; [architecture](docs/context/architecture.md).
- [Métier](docs/context/metier.md), [décisions](docs/context/decisions.md), état de reprise local `docs/context/etat.md` (non versionné).
- [Exploitation](docs/context/infra.md) ; les informations privées d’hébergement restent dans le projet Infra Homelab.
