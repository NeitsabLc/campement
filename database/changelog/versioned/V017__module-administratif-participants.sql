--liquibase formatted sql

--changeset campement:V017-module-administratif-participants
--comment: Ajouter les participants et leurs documents administratifs

CREATE TABLE campement.participant (
    id UUID NOT NULL DEFAULT uuidv7(),
    groupe_id UUID NOT NULL,
    type VARCHAR(10) NOT NULL,
    nom VARCHAR(150) NOT NULL,
    prenom VARCHAR(150) NOT NULL,
    date_naissance DATE NOT NULL,
    telephone_parent_1 VARCHAR(30),
    telephone_parent_2 VARCHAR(30),
    email_parents VARCHAR(254),
    contact_urgence VARCHAR(500),
    qualifications JSONB NOT NULL DEFAULT '[]'::jsonb,
    autre_diplome VARCHAR(255),
    stagiaire_bafa BOOLEAN NOT NULL DEFAULT FALSE,
    date_debut_presence DATE NOT NULL,
    date_fin_presence DATE NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_participant PRIMARY KEY (id),
    CONSTRAINT fk_participant_groupe FOREIGN KEY (groupe_id) REFERENCES campement.groupe(id) ON DELETE CASCADE,
    CONSTRAINT chk_participant_type CHECK (type IN ('jeune', 'adulte')),
    CONSTRAINT chk_participant_dates CHECK (date_fin_presence >= date_debut_presence),
    CONSTRAINT chk_participant_contacts CHECK (
        (type = 'jeune' AND telephone_parent_1 IS NOT NULL AND email_parents IS NOT NULL AND contact_urgence IS NULL)
        OR (type = 'adulte' AND contact_urgence IS NOT NULL AND telephone_parent_1 IS NULL AND telephone_parent_2 IS NULL AND email_parents IS NULL)
    )
);

CREATE INDEX idx_participant_groupe ON campement.participant(groupe_id);
CREATE INDEX idx_participant_groupe_type ON campement.participant(groupe_id, type);

CREATE TABLE campement.document_participant (
    id UUID NOT NULL DEFAULT uuidv7(),
    participant_id UUID NOT NULL,
    type VARCHAR(40) NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_stockage VARCHAR(500) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_document_participant PRIMARY KEY (id),
    CONSTRAINT fk_document_participant FOREIGN KEY (participant_id) REFERENCES campement.participant(id) ON DELETE CASCADE,
    CONSTRAINT chk_document_participant_type CHECK (type IN ('autorisation_depart_camp', 'fiche_sanitaire', 'vaccins', 'qualification'))
);

CREATE INDEX idx_document_participant ON campement.document_participant(participant_id);

--rollback DROP TABLE campement.document_participant; DROP TABLE campement.participant;
