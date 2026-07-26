--liquibase formatted sql

--changeset blecaer:V006
--comment: Modules configurables et mémorisation sécurisée du dernier séjour choisi

ALTER TABLE campement.sejour
    ADD COLUMN module_intendance_actif BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN module_administratif_actif BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE campement.utilisateur
    ADD COLUMN dernier_sejour_id UUID;

ALTER TABLE campement.utilisateur
    ADD CONSTRAINT fk_utilisateur_dernier_sejour
        FOREIGN KEY (dernier_sejour_id)
            REFERENCES campement.sejour (id)
            ON DELETE SET NULL;

CREATE INDEX idx_utilisateur_dernier_sejour
    ON campement.utilisateur (dernier_sejour_id);

--rollback DROP INDEX campement.idx_utilisateur_dernier_sejour;
--rollback ALTER TABLE campement.utilisateur DROP CONSTRAINT fk_utilisateur_dernier_sejour;
--rollback ALTER TABLE campement.utilisateur DROP COLUMN dernier_sejour_id;
--rollback ALTER TABLE campement.sejour DROP COLUMN module_administratif_actif, DROP COLUMN module_intendance_actif;
