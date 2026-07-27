.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_PROD := docker compose -f compose.yaml -f compose.prod.yaml
PHP := $(DOCKER_COMPOSE) exec php
PHP_RUN := $(DOCKER_COMPOSE) run --rm php
LIQUIBASE := $(DOCKER_COMPOSE) --profile tools run --rm liquibase
TEST_DATABASE := campement_test
TEST_DATABASE_URL := jdbc:postgresql://database:5432/$(TEST_DATABASE)

.PHONY: help
help: ## Afficher les commandes disponibles
	@awk 'BEGIN {FS = ":.*##"; printf "\nCommandes disponibles :\n\n"} /^[a-zA-Z0-9_-]+:.*?##/ {printf "  %-25s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

.PHONY: install
install: build up composer-install db-update-dev ## Installer complètement le projet

.PHONY: build
build: ## Construire les images Docker
	$(DOCKER_COMPOSE) build

.PHONY: rebuild
rebuild: ## Reconstruire les images sans cache
	$(DOCKER_COMPOSE) build --no-cache

.PHONY: up
up: ## Démarrer l'environnement
	$(DOCKER_COMPOSE) up -d

.PHONY: down
down: ## Arrêter l'environnement
	$(DOCKER_COMPOSE) down

.PHONY: restart
restart: down up ## Redémarrer l'environnement

.PHONY: ps
ps: ## Afficher l'état des conteneurs
	$(DOCKER_COMPOSE) ps

.PHONY: prod-config
prod-config: ## Valider et afficher la configuration Compose de production
	$(DOCKER_COMPOSE_PROD) config

.PHONY: prod-up
prod-up: ## Démarrer la production avec sa surcharge sécurisée
	$(DOCKER_COMPOSE_PROD) up -d

.PHONY: prod-ps
prod-ps: ## Afficher l'état des conteneurs de production
	$(DOCKER_COMPOSE_PROD) ps

.PHONY: logs
logs: ## Afficher les journaux
	$(DOCKER_COMPOSE) logs -f --tail=100

.PHONY: logs-php
logs-php: ## Afficher les journaux PHP
	$(DOCKER_COMPOSE) logs -f --tail=100 php

.PHONY: logs-nginx
logs-nginx: ## Afficher les journaux Nginx
	$(DOCKER_COMPOSE) logs -f --tail=100 nginx

.PHONY: logs-database
logs-database: ## Afficher les journaux PostgreSQL
	$(DOCKER_COMPOSE) logs -f --tail=100 database

.PHONY: shell
shell: ## Ouvrir un terminal dans PHP
	$(PHP) sh

.PHONY: console
console: ## Exécuter une commande Symfony : make console ARGS="about"
	$(PHP) php bin/console $(ARGS)

.PHONY: composer
composer: ## Exécuter Composer : make composer ARGS="require package"
	$(PHP_RUN) composer $(ARGS)

.PHONY: composer-install
composer-install: ## Installer les dépendances PHP
	$(PHP_RUN) composer install

.PHONY: cache-clear
cache-clear: ## Vider le cache Symfony
	$(PHP) php bin/console cache:clear

.PHONY: assets-compile
assets-compile: ## Recompiler les assets servis directement par Nginx
	$(PHP) php bin/console asset-map:compile

.PHONY: db-validate
db-validate: ## Valider les changelogs Liquibase
	$(LIQUIBASE) validate

.PHONY: db-status
db-status: ## Afficher les changesets en attente
	$(LIQUIBASE) status

.PHONY: db-status-dev
db-status-dev: ## Afficher les changesets de développement en attente
	$(LIQUIBASE) status --context-filter=dev

.PHONY: db-sql
db-sql: ## Afficher le SQL Liquibase sans l'exécuter
	$(LIQUIBASE) update-sql

.PHONY: db-sql-dev
db-sql-dev: ## Afficher le SQL de développement sans l'exécuter
	$(LIQUIBASE) update-sql --context-filter=dev

.PHONY: db-update
db-update: ## Appliquer les migrations communes
	$(LIQUIBASE) update

.PHONY: db-update-dev
db-update-dev: ## Appliquer les migrations communes et de développement
	$(LIQUIBASE) update --context-filter=dev

.PHONY: db-history
db-history: ## Afficher l'historique Liquibase
	docker compose exec database sh -c \
		'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB" \
		-c "SELECT id, author, filename, dateexecuted, exectype FROM public.databasechangelog ORDER BY orderexecuted;"'

.PHONY: db-shell
db-shell: ## Ouvrir une console PostgreSQL
	$(DOCKER_COMPOSE) exec database \
		psql -U "$${POSTGRES_USER}" -d "$${POSTGRES_DB}"

.PHONY: doctrine-validate
doctrine-validate: ## Vérifier le mapping Doctrine
	docker compose exec php php bin/console doctrine:schema:validate --skip-sync

.PHONY: test-db-reset
test-db-reset: ## Recréer et initialiser la base de tests
	$(DOCKER_COMPOSE) exec database sh -c \
		'dropdb --username="$$POSTGRES_USER" --force --if-exists $(TEST_DATABASE)'
	$(DOCKER_COMPOSE) exec database sh -c \
		'createdb --username="$$POSTGRES_USER" --owner="$$POSTGRES_USER" $(TEST_DATABASE)'
	$(DOCKER_COMPOSE) --profile tools run --rm \
		-e LIQUIBASE_COMMAND_URL=$(TEST_DATABASE_URL) \
		liquibase update --context-filter=dev

.PHONY: test
test: test-db-reset ## Recréer la base de tests puis exécuter les tests
	$(PHP) php bin/phpunit

.PHONY: reset
reset: ## Supprimer les conteneurs et la base locale
	$(DOCKER_COMPOSE) down --volumes --remove-orphans

.PHONY: clean
clean: ## Nettoyer les fichiers temporaires Symfony
	rm -rf app/var/cache/*
	rm -rf app/var/log/*

.PHONY: db-check-connection
db-check-connection: ## Vérifier la connexion Doctrine à PostgreSQL
	$(PHP) php bin/console dbal:run-sql \
		"SELECT current_database(), current_user, current_schema(), current_setting('search_path')"
