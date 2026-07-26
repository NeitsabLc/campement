--liquibase formatted sql

--changeset blecaer:V001
CREATE SCHEMA IF NOT EXISTS campement AUTHORIZATION campement;

ALTER SCHEMA campement OWNER TO campement;

COMMENT ON SCHEMA campement IS
    'Schéma principal de l’application Campement';

ALTER ROLE campement
    IN DATABASE campement
    SET search_path TO campement, public;

--rollback ALTER ROLE campement IN DATABASE campement RESET search_path;
--rollback COMMENT ON SCHEMA campement IS NULL;
