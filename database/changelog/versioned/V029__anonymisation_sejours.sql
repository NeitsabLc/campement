--liquibase formatted sql
--changeset campement:V029-anonymisation-sejours splitStatements:true endDelimiter:;
--comment: Mémorise l'anonymisation des séjours afin de rendre le traitement automatique idempotent

ALTER TABLE campement.sejour
    ADD COLUMN anonymise_at TIMESTAMP WITH TIME ZONE NULL;

CREATE INDEX idx_sejour_anonymisation
    ON campement.sejour(date_fin, anonymise_at);

--rollback DROP INDEX campement.idx_sejour_anonymisation; ALTER TABLE campement.sejour DROP COLUMN anonymise_at;
