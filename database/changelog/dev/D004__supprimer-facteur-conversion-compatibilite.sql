--liquibase formatted sql

--changeset campement:D004-supprimer-facteur-conversion-compatibilite context:dev
--comment: Retrait de la colonne transitoire après l'exécution des données historiques D003

ALTER TABLE campement.unite
    DROP CONSTRAINT chk_unite_conversion,
    DROP COLUMN facteur_conversion;

--rollback ALTER TABLE campement.unite ADD COLUMN facteur_conversion NUMERIC(12,6) NOT NULL DEFAULT 1, ADD CONSTRAINT chk_unite_conversion CHECK (facteur_conversion > 0);
