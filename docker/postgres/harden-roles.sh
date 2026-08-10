#!/bin/sh

set -eu

mode=${1:-prepare}

if [ "$mode" != "prepare" ] && [ "$mode" != "finalize" ]; then
    echo "Usage: campement-harden-roles prepare|finalize" >&2
    exit 2
fi

for variable in POSTGRES_APP_PASSWORD POSTGRES_MIGRATOR_PASSWORD POSTGRES_BACKUP_PASSWORD; do
    case "$variable" in
        POSTGRES_APP_PASSWORD) valeur=${POSTGRES_APP_PASSWORD:-} ;;
        POSTGRES_MIGRATOR_PASSWORD) valeur=${POSTGRES_MIGRATOR_PASSWORD:-} ;;
        POSTGRES_BACKUP_PASSWORD) valeur=${POSTGRES_BACKUP_PASSWORD:-} ;;
    esac
    if [ -z "$valeur" ] || [ "$valeur" = "change-me" ]; then
        echo "$variable doit contenir un secret fort avant le durcissement." >&2
        exit 2
    fi
done

psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --single-transaction --set=ON_ERROR_STOP=1 \
    --set=database_name="$POSTGRES_DB" \
    --set=app_password="$POSTGRES_APP_PASSWORD" \
    --set=migrator_password="$POSTGRES_MIGRATOR_PASSWORD" \
    --set=backup_password="$POSTGRES_BACKUP_PASSWORD" <<'SQL'
SELECT format('CREATE ROLE campement_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'app_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'campement_app') \gexec
SELECT format('CREATE ROLE campement_migrator LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'migrator_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'campement_migrator') \gexec
SELECT format('CREATE ROLE campement_backup LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L', :'backup_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'campement_backup') \gexec
SELECT 'CREATE ROLE campement_admin WITH LOGIN SUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION BYPASSRLS'
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'campement_admin') \gexec

SELECT format('ALTER ROLE campement_app PASSWORD %L', :'app_password') \gexec
SELECT format('ALTER ROLE campement_migrator PASSWORD %L', :'migrator_password') \gexec
SELECT format('ALTER ROLE campement_backup PASSWORD %L', :'backup_password') \gexec
ALTER ROLE campement_admin SUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION BYPASSRLS PASSWORD NULL;

SELECT format('GRANT CONNECT ON DATABASE %I TO campement_app, campement_migrator, campement_backup', :'database_name') \gexec
GRANT USAGE ON SCHEMA campement TO campement_app, campement_backup;
GRANT USAGE, CREATE ON SCHEMA campement, public TO campement_migrator;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA campement TO campement_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA campement TO campement_app;
GRANT SELECT ON ALL TABLES IN SCHEMA campement TO campement_backup;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA campement TO campement_backup;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA campement, public TO campement_migrator;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA campement, public TO campement_migrator;

ALTER DEFAULT PRIVILEGES FOR ROLE campement_migrator IN SCHEMA campement
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO campement_app;
ALTER DEFAULT PRIVILEGES FOR ROLE campement_migrator IN SCHEMA campement
    GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO campement_app;
ALTER DEFAULT PRIVILEGES FOR ROLE campement_migrator IN SCHEMA campement
    GRANT SELECT ON TABLES TO campement_backup;
ALTER DEFAULT PRIVILEGES FOR ROLE campement_migrator IN SCHEMA campement
    GRANT SELECT ON SEQUENCES TO campement_backup;

SELECT format('ALTER ROLE campement_app IN DATABASE %I SET search_path TO campement, public', :'database_name') \gexec
SELECT format('ALTER ROLE campement_migrator IN DATABASE %I SET search_path TO campement, public', :'database_name') \gexec
SELECT format('ALTER ROLE campement_backup IN DATABASE %I SET search_path TO campement, public', :'database_name') \gexec
SELECT pg_reload_conf();
SQL

if [ "$mode" = "prepare" ]; then
    echo "Rôles préparés. Basculez et vérifiez l'application, Liquibase et les sauvegardes avant finalize."
    exit 0
fi

psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --single-transaction --set=ON_ERROR_STOP=1 \
    --set=database_name="$POSTGRES_DB" <<'SQL'
SELECT format('ALTER DATABASE %I OWNER TO campement_migrator', :'database_name') \gexec
ALTER SCHEMA campement OWNER TO campement_migrator;

-- Le compte créé par l'image PostgreSQL peut posséder des objets système
-- protégés. On transfère donc uniquement les objets de l'application et les
-- tables de suivi Liquibase, sans REASSIGN OWNED global.
SELECT format(
    'ALTER %s %I.%I OWNER TO campement_migrator',
    CASE classe.relkind
        WHEN 'S' THEN 'SEQUENCE'
        WHEN 'v' THEN 'VIEW'
        WHEN 'm' THEN 'MATERIALIZED VIEW'
        WHEN 'f' THEN 'FOREIGN TABLE'
        ELSE 'TABLE'
    END,
    espace.nspname,
    classe.relname
)
FROM pg_class classe
JOIN pg_namespace espace ON espace.oid = classe.relnamespace
WHERE espace.nspname IN ('campement', 'public')
  AND classe.relkind IN ('r', 'p', 'S', 'v', 'm', 'f')
  AND pg_get_userbyid(classe.relowner) = 'campement'
ORDER BY espace.nspname, classe.relkind, classe.relname
\gexec

SELECT format(
    'ALTER %s %I.%I(%s) OWNER TO campement_migrator',
    CASE procedure.prokind WHEN 'p' THEN 'PROCEDURE' WHEN 'a' THEN 'AGGREGATE' ELSE 'FUNCTION' END,
    espace.nspname,
    procedure.proname,
    pg_get_function_identity_arguments(procedure.oid)
)
FROM pg_proc procedure
JOIN pg_namespace espace ON espace.oid = procedure.pronamespace
WHERE espace.nspname IN ('campement', 'public')
  AND pg_get_userbyid(procedure.proowner) = 'campement'
ORDER BY espace.nspname, procedure.proname
\gexec

SELECT format('ALTER %s %I.%I OWNER TO campement_migrator',
    CASE type.typtype WHEN 'd' THEN 'DOMAIN' ELSE 'TYPE' END,
    espace.nspname,
    type.typname
)
FROM pg_type type
JOIN pg_namespace espace ON espace.oid = type.typnamespace
WHERE espace.nspname IN ('campement', 'public')
  AND type.typrelid = 0
  AND type.typtype IN ('d', 'e', 'm', 'r')
  AND pg_get_userbyid(type.typowner) = 'campement'
ORDER BY espace.nspname, type.typname
\gexec

REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA campement, public FROM campement;
REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA campement, public FROM campement;
REVOKE ALL ON SCHEMA campement FROM campement;
-- PostgreSQL interdit de retirer SUPERUSER au rôle d'amorçage. NOLOGIN le rend
-- inutilisable par l'application et par le réseau, sans contourner cette garde.
ALTER ROLE campement NOLOGIN NOCREATEDB NOCREATEROLE NOREPLICATION;
SQL

echo "Durcissement finalisé : la connexion au rôle historique campement est désactivée."
