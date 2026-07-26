-- liquibase formatted sql

-- changeset campement:V005-index-jeton-reinitialisation
DROP INDEX campement.uq_utilisateur_jeton_reinitialisation;

CREATE INDEX idx_utilisateur_jeton_reinitialisation
    ON campement.utilisateur (jeton_reinitialisation);
