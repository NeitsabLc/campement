-- liquibase formatted sql

-- changeset campement:V004-securiser-mots-de-passe-utilisateur
ALTER TABLE campement.utilisateur
    ADD COLUMN changement_mot_de_passe_requis BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN jeton_reinitialisation VARCHAR(64),
    ADD COLUMN expiration_jeton_reinitialisation TIMESTAMPTZ;

CREATE UNIQUE INDEX uq_utilisateur_jeton_reinitialisation
    ON campement.utilisateur (jeton_reinitialisation)
    WHERE jeton_reinitialisation IS NOT NULL;

ALTER TABLE campement.utilisateur
    ADD CONSTRAINT chk_utilisateur_affectation_role
        CHECK (
            (roles ->> 0 = 'ROLE_GESTIONNAIRE' AND groupe_id IS NULL)
            OR (roles ->> 0 = 'ROLE_GROUPE' AND groupe_id IS NOT NULL)
            OR (roles ->> 0 IN ('ROLE_ADMIN', 'ROLE_TECHNIQUE') AND groupe_id IS NULL)
        );
