--liquibase formatted sql

--changeset campement:D000-compatibilite-facteur-conversion context:dev
--comment: Compatibilité transitoire avec D003, immuable et historiquement fondé sur facteur_conversion

ALTER TABLE campement.unite
    ADD COLUMN facteur_conversion NUMERIC(12,6) NOT NULL DEFAULT 1,
    ADD CONSTRAINT chk_unite_conversion CHECK (facteur_conversion > 0);

--rollback ALTER TABLE campement.unite DROP CONSTRAINT chk_unite_conversion, DROP COLUMN facteur_conversion;
