--liquibase formatted sql

--changeset campement:V007-distribution-publique-multi-sejour
ALTER TABLE campement.sejour
    ADD COLUMN distribution_publique_active BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN jeton_distribution_publique UUID NOT NULL DEFAULT uuidv7();

ALTER TABLE campement.sejour
    ADD CONSTRAINT uq_sejour_jeton_distribution_publique UNIQUE (jeton_distribution_publique);

--rollback ALTER TABLE campement.sejour DROP CONSTRAINT uq_sejour_jeton_distribution_publique;
--rollback ALTER TABLE campement.sejour DROP COLUMN jeton_distribution_publique, DROP COLUMN distribution_publique_active;
