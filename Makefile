.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_PROD := $(DOCKER_COMPOSE) -f compose.yaml -f compose.prod.yaml
RELEASE_ENV ?= .env.release
DOCKER_COMPOSE_RELEASE := $(DOCKER_COMPOSE) --env-file .env --env-file $(RELEASE_ENV) -f compose.yaml -f compose.prod.yaml -f compose.release.yaml
PHP := $(DOCKER_COMPOSE) exec php
PHP_RUN := $(DOCKER_COMPOSE) run --rm php
LIQUIBASE := $(DOCKER_COMPOSE) --profile tools run --rm liquibase
TEST_DATABASE := campement_test
TEST_DATABASE_URL := jdbc:postgresql://database:5432/$(TEST_DATABASE)

.PHONY: help install build rebuild up down restart ps \
	prod-config prod-up prod-ps \
	release-config release-verify release-pull release-db-status release-db-update release-up release-ps \
	logs shell console composer composer-install cache-clear assets-compile \
	db-validate db-status db-status-dev db-sql db-sql-dev db-update db-update-dev db-history db-shell db-check-connection \
	doctrine-validate style style-fix analyse-statique \
	test-accessibility test-e2e test-db-reset test reset clean purge-data \
	backup-now backup-restore-test maintenance-now prod-db-roles-prepare prod-db-roles-finalize

help: ## Afficher les commandes disponibles
	@awk 'BEGIN {FS = ":.*##"; printf "\nCommandes disponibles :\n\n"} /^[a-zA-Z0-9_-]+:.*?##/ {printf "  %-25s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: build up composer-install db-update-dev ## Installer complètement le projet

build: ## Construire les images Docker
	$(DOCKER_COMPOSE) build

rebuild: ## Reconstruire les images sans cache
	$(DOCKER_COMPOSE) build --no-cache

up: ## Démarrer l'environnement
	$(DOCKER_COMPOSE) up -d

down: ## Arrêter l'environnement
	$(DOCKER_COMPOSE) down

restart: down up ## Redémarrer l'environnement

ps: ## Afficher l'état des conteneurs
	$(DOCKER_COMPOSE) ps

prod-config: ## Valider silencieusement la configuration Compose de production
	@$(DOCKER_COMPOSE_PROD) config --quiet

prod-up: ## Démarrer la production avec sa surcharge sécurisée
	$(DOCKER_COMPOSE_PROD) up -d

prod-ps: ## Afficher l'état des conteneurs de production
	$(DOCKER_COMPOSE_PROD) ps

release-config: ## Valider la configuration de livraison utilisant GHCR
	@$(DOCKER_COMPOSE_RELEASE) config --quiet

release-verify: ## Vérifier les digests et signatures Sigstore des images GHCR
	@set -a; . ./$(RELEASE_ENV); set +a; ./scripts/verify-release-images.sh

release-pull: release-config release-verify ## Télécharger manuellement les cinq images vérifiées
	$(DOCKER_COMPOSE_RELEASE) --profile tools --profile backup pull php nginx database liquibase backup

release-db-status: release-pull ## Contrôler les migrations avec l'image Liquibase livrée
	$(DOCKER_COMPOSE_RELEASE) --profile tools run --rm liquibase status

release-db-update: release-pull ## Appliquer les migrations avec l'image Liquibase livrée
	$(DOCKER_COMPOSE_RELEASE) --profile tools run --rm liquibase update

release-up: release-pull ## Démarrer manuellement les services persistants depuis les images GHCR
	$(DOCKER_COMPOSE_RELEASE) up -d --no-build --wait --wait-timeout 120 database php nginx

release-ps: ## Afficher l'état des conteneurs issus des images GHCR
	$(DOCKER_COMPOSE_RELEASE) ps

logs: ## Afficher les journaux : make logs SERVICE=php
	$(DOCKER_COMPOSE) logs -f --tail=100 $(SERVICE)

shell: ## Ouvrir un terminal dans PHP
	$(PHP) sh

console: ## Exécuter une commande Symfony : make console ARGS="about"
	$(PHP) php bin/console $(ARGS)

composer: ## Exécuter Composer : make composer ARGS="require package"
	$(PHP_RUN) composer $(ARGS)

composer-install: ## Installer les dépendances PHP
	$(PHP_RUN) composer install

cache-clear: ## Vider le cache Symfony
	$(PHP) php bin/console cache:clear

assets-compile: ## Recompiler les assets servis directement par Nginx
	$(PHP) php bin/console asset-map:compile

db-validate: ## Valider les changelogs Liquibase
	$(LIQUIBASE) validate

db-status: ## Afficher les changesets en attente
	$(LIQUIBASE) status

db-status-dev: ## Afficher les changesets de développement en attente
	$(LIQUIBASE) status --context-filter=dev

db-sql: ## Afficher le SQL Liquibase sans l'exécuter
	$(LIQUIBASE) update-sql

db-sql-dev: ## Afficher le SQL de développement sans l'exécuter
	$(LIQUIBASE) update-sql --context-filter=dev

db-update: ## Appliquer les migrations communes
	$(LIQUIBASE) update

db-update-dev: ## Appliquer les migrations communes et de développement
	$(LIQUIBASE) update --context-filter=dev

db-history: ## Afficher l'historique Liquibase
	$(DOCKER_COMPOSE) exec database sh -c \
		'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB" \
		-c "SELECT id, author, filename, dateexecuted, exectype FROM public.databasechangelog ORDER BY orderexecuted;"'

db-shell: ## Ouvrir une console PostgreSQL
	$(DOCKER_COMPOSE) exec database \
		psql -U "$${POSTGRES_USER}" -d "$${POSTGRES_DB}"

doctrine-validate: ## Vérifier le mapping Doctrine
	$(DOCKER_COMPOSE) exec php php bin/console doctrine:schema:validate --skip-sync

style: ## Contrôler le style PHP sans modifier les fichiers
	$(PHP) composer lint:php

style-fix: ## Corriger automatiquement le style PHP
	$(PHP) composer fix:php

analyse-statique: ## Analyser le code PHP avec PHPStan
	$(PHP) php bin/console cache:warmup --env=dev
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

test-accessibility: db-update-dev assets-compile ## Tester l'accessibilité sur les données de développement
	npm run test:accessibility

test-e2e: db-update-dev assets-compile ## Tester les parcours E2E sur les données de développement
	npm run test:e2e

test-db-reset: ## Recréer et initialiser la base de tests
	$(DOCKER_COMPOSE) exec database sh -c \
		'dropdb --username="$$POSTGRES_USER" --force --if-exists $(TEST_DATABASE)'
	$(DOCKER_COMPOSE) exec database sh -c \
		'createdb --username="$$POSTGRES_USER" --owner="$$POSTGRES_USER" $(TEST_DATABASE)'
	$(DOCKER_COMPOSE) --profile tools run --rm \
		-e LIQUIBASE_COMMAND_URL=$(TEST_DATABASE_URL) \
		liquibase update --context-filter=dev

test: test-db-reset ## Recréer la base de tests puis exécuter les tests
	$(PHP) php bin/phpunit

reset: ## Supprimer les conteneurs et la base locale
	$(DOCKER_COMPOSE) down --volumes --remove-orphans

clean: ## Nettoyer les fichiers temporaires Symfony
	rm -rf app/var/cache/*
	rm -rf app/var/log/*

purge-data: ## Appliquer immédiatement les délais de conservation
	$(PHP) php bin/console app:sejours:anonymiser
	$(PHP) php bin/console app:donnees:purger

backup-now: ## Créer immédiatement une sauvegarde ponctuelle
	$(DOCKER_COMPOSE_PROD) --profile backup run --rm backup

backup-restore-test: ## Chiffrer puis restaurer la base et les documents dans un environnement jetable
	./scripts/ci-backup-restore.sh

maintenance-now: ## Exécuter immédiatement un cycle de maintenance de production
	$(DOCKER_COMPOSE_PROD) --profile maintenance run --rm maintenance

prod-db-roles-prepare: ## Préparer les rôles PostgreSQL limités sans retirer les accès existants
	$(DOCKER_COMPOSE_PROD) exec database campement-harden-roles prepare

prod-db-roles-finalize: ## Retirer définitivement les privilèges du rôle PostgreSQL historique
	$(DOCKER_COMPOSE_PROD) exec database campement-harden-roles finalize

db-check-connection: ## Vérifier la connexion Doctrine à PostgreSQL
	$(PHP) php bin/console dbal:run-sql \
		"SELECT current_database(), current_user, current_schema(), current_setting('search_path')"
