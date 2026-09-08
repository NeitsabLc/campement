#!/bin/sh

set -eu

mode=${1:-prepare}

if [ "$mode" != "prepare" ] && [ "$mode" != "finalize" ]; then
    echo "Usage: campement-harden-roles prepare|finalize" >&2
    exit 2
fi

for variable in \
    POSTGRES_APP_USER POSTGRES_APP_PASSWORD \
    POSTGRES_MIGRATOR_USER POSTGRES_MIGRATOR_PASSWORD \
    POSTGRES_BACKUP_USER POSTGRES_BACKUP_PASSWORD \
    POSTGRES_HEALTHCHECK_USER POSTGRES_HEALTHCHECK_PASSWORD
do
    case "$variable" in
        POSTGRES_APP_USER) valeur=${POSTGRES_APP_USER:-} ;;
        POSTGRES_APP_PASSWORD) valeur=${POSTGRES_APP_PASSWORD:-} ;;
        POSTGRES_MIGRATOR_USER) valeur=${POSTGRES_MIGRATOR_USER:-} ;;
        POSTGRES_MIGRATOR_PASSWORD) valeur=${POSTGRES_MIGRATOR_PASSWORD:-} ;;
        POSTGRES_BACKUP_USER) valeur=${POSTGRES_BACKUP_USER:-} ;;
        POSTGRES_BACKUP_PASSWORD) valeur=${POSTGRES_BACKUP_PASSWORD:-} ;;
        POSTGRES_HEALTHCHECK_USER) valeur=${POSTGRES_HEALTHCHECK_USER:-} ;;
        POSTGRES_HEALTHCHECK_PASSWORD) valeur=${POSTGRES_HEALTHCHECK_PASSWORD:-} ;;
    esac
    if [ -z "$valeur" ] || [ "$valeur" = "change-me" ]; then
        echo "$variable doit être renseignée avant le durcissement." >&2
        exit 2
    fi
done

if [ "$POSTGRES_APP_USER" = "$POSTGRES_MIGRATOR_USER" ] \
    || [ "$POSTGRES_APP_USER" = "$POSTGRES_BACKUP_USER" ] \
    || [ "$POSTGRES_APP_USER" = "$POSTGRES_HEALTHCHECK_USER" ] \
    || [ "$POSTGRES_MIGRATOR_USER" = "$POSTGRES_BACKUP_USER" ] \
    || [ "$POSTGRES_MIGRATOR_USER" = "$POSTGRES_HEALTHCHECK_USER" ] \
    || [ "$POSTGRES_BACKUP_USER" = "$POSTGRES_HEALTHCHECK_USER" ] \
    || [ "$POSTGRES_USER" = "$POSTGRES_APP_USER" ] \
    || [ "$POSTGRES_USER" = "$POSTGRES_MIGRATOR_USER" ] \
    || [ "$POSTGRES_USER" = "$POSTGRES_BACKUP_USER" ] \
    || [ "$POSTGRES_USER" = "$POSTGRES_HEALTHCHECK_USER" ]; then
    echo "Les rôles PostgreSQL d’amorçage et de production doivent être distincts." >&2
    exit 2
fi

PGPASSWORD="$POSTGRES_PASSWORD" psql --host=127.0.0.1 --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --single-transaction --set=ON_ERROR_STOP=1 \
    --set=database_name="$POSTGRES_DB" \
    --set=app_user="$POSTGRES_APP_USER" \
    --set=app_password="$POSTGRES_APP_PASSWORD" \
    --set=migrator_user="$POSTGRES_MIGRATOR_USER" \
    --set=migrator_password="$POSTGRES_MIGRATOR_PASSWORD" \
    --set=backup_user="$POSTGRES_BACKUP_USER" \
    --set=backup_password="$POSTGRES_BACKUP_PASSWORD" \
    --set=admin_user="$POSTGRES_HEALTHCHECK_USER" \
    --set=admin_password="$POSTGRES_HEALTHCHECK_PASSWORD" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'app_user', :'app_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'app_user') \gexec
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'migrator_user', :'migrator_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'migrator_user') \gexec
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'backup_user', :'backup_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'backup_user') \gexec
SELECT format('CREATE ROLE %I WITH LOGIN SUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION BYPASSRLS PASSWORD %L', :'admin_user', :'admin_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'admin_user') \gexec

SELECT format('ALTER ROLE %I PASSWORD %L', :'app_user', :'app_password') \gexec
SELECT format('ALTER ROLE %I PASSWORD %L', :'migrator_user', :'migrator_password') \gexec
SELECT format('ALTER ROLE %I PASSWORD %L', :'backup_user', :'backup_password') \gexec
SELECT format('ALTER ROLE %I SUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION BYPASSRLS PASSWORD %L', :'admin_user', :'admin_password') \gexec

SELECT format('GRANT CONNECT ON DATABASE %I TO %I, %I, %I', :'database_name', :'app_user', :'migrator_user', :'backup_user') \gexec
SELECT format('GRANT USAGE ON SCHEMA campement TO %I', :'app_user') \gexec
SELECT format('GRANT USAGE ON SCHEMA campement, public TO %I', :'backup_user') \gexec
SELECT format('GRANT USAGE, CREATE ON SCHEMA campement, public TO %I', :'migrator_user') \gexec
SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA campement TO %I', :'app_user') \gexec
SELECT format('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA campement TO %I', :'app_user') \gexec
SELECT format('GRANT SELECT ON ALL TABLES IN SCHEMA campement, public TO %I', :'backup_user') \gexec
SELECT format('GRANT SELECT ON ALL SEQUENCES IN SCHEMA campement, public TO %I', :'backup_user') \gexec
SELECT format('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA campement, public TO %I', :'migrator_user') \gexec
SELECT format('GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA campement, public TO %I', :'migrator_user') \gexec

SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA campement GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', :'migrator_user', :'app_user') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA campement GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO %I', :'migrator_user', :'app_user') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA campement GRANT SELECT ON TABLES TO %I', :'migrator_user', :'backup_user') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA campement GRANT SELECT ON SEQUENCES TO %I', :'migrator_user', :'backup_user') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public GRANT SELECT ON TABLES TO %I', :'migrator_user', :'backup_user') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public GRANT SELECT ON SEQUENCES TO %I', :'migrator_user', :'backup_user') \gexec

SELECT format('ALTER ROLE %I IN DATABASE %I SET search_path TO campement, public', :'app_user', :'database_name') \gexec
SELECT format('ALTER ROLE %I IN DATABASE %I SET search_path TO campement, public', :'migrator_user', :'database_name') \gexec
SELECT format('ALTER ROLE %I IN DATABASE %I SET search_path TO campement, public', :'backup_user', :'database_name') \gexec
SELECT pg_reload_conf();
SQL

if [ "$mode" = "prepare" ]; then
    echo "Rôles préparés. Basculez et vérifiez l'application, Liquibase et les sauvegardes avant finalize."
    exit 0
fi

PGPASSWORD="$POSTGRES_PASSWORD" psql --host=127.0.0.1 --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --single-transaction --set=ON_ERROR_STOP=1 \
    --set=database_name="$POSTGRES_DB" \
    --set=bootstrap_user="$POSTGRES_USER" \
    --set=migrator_user="$POSTGRES_MIGRATOR_USER" <<'SQL'
SELECT format('ALTER DATABASE %I OWNER TO %I', :'database_name', :'migrator_user') \gexec
SELECT format('ALTER SCHEMA campement OWNER TO %I', :'migrator_user') \gexec

-- Le compte créé par l'image PostgreSQL peut posséder des objets système
-- protégés. On transfère donc uniquement les objets de l'application et les
-- tables de suivi Liquibase, sans REASSIGN OWNED global.
SELECT format(
    'ALTER %s %I.%I OWNER TO %I',
    CASE classe.relkind
        WHEN 'S' THEN 'SEQUENCE'
        WHEN 'v' THEN 'VIEW'
        WHEN 'm' THEN 'MATERIALIZED VIEW'
        WHEN 'f' THEN 'FOREIGN TABLE'
        ELSE 'TABLE'
    END,
    espace.nspname,
    classe.relname,
    :'migrator_user'
)
FROM pg_class classe
JOIN pg_namespace espace ON espace.oid = classe.relnamespace
WHERE espace.nspname IN ('campement', 'public')
  AND classe.relkind IN ('r', 'p', 'S', 'v', 'm', 'f')
  AND pg_get_userbyid(classe.relowner) = :'bootstrap_user'
ORDER BY espace.nspname, classe.relkind, classe.relname
\gexec

SELECT format(
    'ALTER %s %I.%I(%s) OWNER TO %I',
    CASE procedure.prokind WHEN 'p' THEN 'PROCEDURE' WHEN 'a' THEN 'AGGREGATE' ELSE 'FUNCTION' END,
    espace.nspname,
    procedure.proname,
    pg_get_function_identity_arguments(procedure.oid),
    :'migrator_user'
)
FROM pg_proc procedure
JOIN pg_namespace espace ON espace.oid = procedure.pronamespace
WHERE espace.nspname IN ('campement', 'public')
  AND pg_get_userbyid(procedure.proowner) = :'bootstrap_user'
ORDER BY espace.nspname, procedure.proname
\gexec

SELECT format(
    'ALTER %s %I.%I OWNER TO %I',
    CASE type.typtype WHEN 'd' THEN 'DOMAIN' ELSE 'TYPE' END,
    espace.nspname,
    type.typname,
    :'migrator_user'
)
FROM pg_type type
JOIN pg_namespace espace ON espace.oid = type.typnamespace
WHERE espace.nspname IN ('campement', 'public')
  AND type.typrelid = 0
  AND type.typtype IN ('d', 'e', 'm', 'r')
  AND pg_get_userbyid(type.typowner) = :'bootstrap_user'
ORDER BY espace.nspname, type.typname
\gexec

SELECT format('REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA campement, public FROM %I', :'bootstrap_user') \gexec
SELECT format('REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA campement, public FROM %I', :'bootstrap_user') \gexec
SELECT format('REVOKE ALL ON SCHEMA campement FROM %I', :'bootstrap_user') \gexec
-- PostgreSQL interdit de retirer SUPERUSER au rôle d'amorçage. NOLOGIN le rend
-- inutilisable par l'application et par le réseau, sans contourner cette garde.
SELECT format('ALTER ROLE %I NOLOGIN NOCREATEDB NOCREATEROLE NOREPLICATION', :'bootstrap_user') \gexec
SQL

echo "Durcissement finalisé : la connexion au rôle historique $POSTGRES_USER est désactivée."
