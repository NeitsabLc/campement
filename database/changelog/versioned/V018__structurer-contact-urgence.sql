--liquibase formatted sql

--changeset campement:V018-structurer-contact-urgence
--comment: Séparer le nom et le téléphone du contact d'urgence des adultes

ALTER TABLE campement.participant DROP CONSTRAINT chk_participant_contacts;
ALTER TABLE campement.participant
    ADD COLUMN contact_urgence_nom_prenom VARCHAR(300),
    ADD COLUMN contact_urgence_telephone VARCHAR(30);

UPDATE campement.participant
SET contact_urgence_nom_prenom = contact_urgence,
    contact_urgence_telephone = 'À renseigner'
WHERE type = 'adulte';

ALTER TABLE campement.participant
    DROP COLUMN contact_urgence,
    ADD CONSTRAINT chk_participant_contacts CHECK (
        (type = 'jeune' AND telephone_parent_1 IS NOT NULL AND email_parents IS NOT NULL
            AND contact_urgence_nom_prenom IS NULL AND contact_urgence_telephone IS NULL)
        OR (type = 'adulte' AND contact_urgence_nom_prenom IS NOT NULL AND contact_urgence_telephone IS NOT NULL
            AND telephone_parent_1 IS NULL AND telephone_parent_2 IS NULL AND email_parents IS NULL)
    );

--rollback ALTER TABLE campement.participant DROP CONSTRAINT chk_participant_contacts; ALTER TABLE campement.participant ADD COLUMN contact_urgence VARCHAR(500); UPDATE campement.participant SET contact_urgence = contact_urgence_nom_prenom WHERE type = 'adulte'; ALTER TABLE campement.participant DROP COLUMN contact_urgence_nom_prenom, DROP COLUMN contact_urgence_telephone; ALTER TABLE campement.participant ADD CONSTRAINT chk_participant_contacts CHECK ((type = 'jeune' AND telephone_parent_1 IS NOT NULL AND email_parents IS NOT NULL AND contact_urgence IS NULL) OR (type = 'adulte' AND contact_urgence IS NOT NULL AND telephone_parent_1 IS NULL AND telephone_parent_2 IS NULL AND email_parents IS NULL));
