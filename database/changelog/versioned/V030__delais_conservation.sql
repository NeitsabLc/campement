--liquibase formatted sql

--changeset campement:V030-delais-conservation
--comment: Date la désactivation des comptes afin de permettre leur purge après un mois

ALTER TABLE campement.utilisateur
    ADD COLUMN desactive_at TIMESTAMP WITH TIME ZONE NULL;

UPDATE campement.utilisateur
SET desactive_at = CURRENT_TIMESTAMP
WHERE actif = FALSE;

CREATE INDEX idx_utilisateur_purge
    ON campement.utilisateur(desactive_at)
    WHERE actif = FALSE;

--rollback DROP INDEX campement.idx_utilisateur_purge; ALTER TABLE campement.utilisateur DROP COLUMN desactive_at;
