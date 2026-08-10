--liquibase formatted sql

--changeset campement:V031-idempotence-distribution
--comment: Empêche une double confirmation technique de la même distribution publique

ALTER TABLE campement.mouvement_stock
    ADD COLUMN cle_soumission UUID NULL,
    ADD CONSTRAINT uq_mouvement_stock_cle_soumission UNIQUE (cle_soumission);

--rollback ALTER TABLE campement.mouvement_stock DROP CONSTRAINT uq_mouvement_stock_cle_soumission, DROP COLUMN cle_soumission;
