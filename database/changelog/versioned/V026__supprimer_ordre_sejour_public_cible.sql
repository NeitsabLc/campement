--liquibase formatted sql

--changeset campement:V026-supprimer-ordre-sejour-public-cible splitStatements:true endDelimiter:;
--comment: Utilise l'ordre global de public_cible et supprime l'ordre redondant par séjour

ALTER TABLE campement.sejour_public_cible
    DROP CONSTRAINT chk_sejour_public_cible_ordre,
    DROP COLUMN ordre;

--rollback ALTER TABLE campement.sejour_public_cible ADD COLUMN ordre SMALLINT NOT NULL DEFAULT 0; ALTER TABLE campement.sejour_public_cible ADD CONSTRAINT chk_sejour_public_cible_ordre CHECK (ordre >= 0);
