--liquibase formatted sql

--changeset campement:V021-presence-unites-participantes
ALTER TABLE campement.groupe
    ADD COLUMN date_debut_presence DATE,
    ADD COLUMN date_fin_presence DATE;

UPDATE campement.groupe AS groupe
SET date_debut_presence = sejour.date_debut,
    date_fin_presence = sejour.date_fin
FROM campement.sejour AS sejour
WHERE sejour.id = groupe.sejour_id;

ALTER TABLE campement.groupe
    ALTER COLUMN date_debut_presence SET NOT NULL,
    ALTER COLUMN date_fin_presence SET NOT NULL,
    ADD CONSTRAINT chk_groupe_dates_presence CHECK (date_fin_presence >= date_debut_presence);

CREATE INDEX idx_groupe_presence
    ON campement.groupe(sejour_id, date_debut_presence, date_fin_presence)
    WHERE actif = TRUE;

--rollback DROP INDEX campement.idx_groupe_presence; ALTER TABLE campement.groupe DROP CONSTRAINT chk_groupe_dates_presence, DROP COLUMN date_debut_presence, DROP COLUMN date_fin_presence;
