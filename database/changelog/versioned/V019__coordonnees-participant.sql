--liquibase formatted sql

--changeset campement:V019-coordonnees-participant
--comment: Ajouter les coordonnées personnelles des participants adultes

ALTER TABLE campement.participant
    ADD COLUMN telephone VARCHAR(30),
    ADD COLUMN email VARCHAR(254);

--rollback ALTER TABLE campement.participant DROP COLUMN email, DROP COLUMN telephone;
