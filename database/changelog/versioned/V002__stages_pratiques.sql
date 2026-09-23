--liquibase formatted sql

--changeset campement:V002-stages-pratiques splitStatements:false
--comment: Module optionnel de suivi des stages pratiques BAFA et BAFD

ALTER TABLE campement.sejour
    ADD COLUMN module_stages_pratiques_actif boolean DEFAULT false NOT NULL;

ALTER TABLE campement.participant
    ADD COLUMN stagiaire_bafd boolean DEFAULT false NOT NULL,
    ADD COLUMN avis_stage_sgdf text,
    ADD COLUMN avis_stage_formation text,
    ADD CONSTRAINT chk_participant_stage_pratique
        CHECK (NOT (stagiaire_bafa AND stagiaire_bafd));

CREATE TABLE campement.commentaire_stage_pratique (
    id uuid DEFAULT uuidv7() NOT NULL,
    participant_id uuid NOT NULL,
    date_commentaire date NOT NULL,
    commentaire text NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT commentaire_stage_pratique_pkey PRIMARY KEY (id),
    CONSTRAINT uq_commentaire_stage_participant_date UNIQUE (participant_id, date_commentaire),
    CONSTRAINT chk_commentaire_stage_pratique_non_vide CHECK (length(btrim(commentaire)) > 0),
    CONSTRAINT fk_commentaire_stage_pratique_participant
        FOREIGN KEY (participant_id) REFERENCES campement.participant(id) ON DELETE CASCADE
);

CREATE INDEX idx_commentaire_stage_pratique_participant
    ON campement.commentaire_stage_pratique (participant_id);

--rollback DROP TABLE campement.commentaire_stage_pratique;
--rollback ALTER TABLE campement.participant DROP CONSTRAINT chk_participant_stage_pratique, DROP COLUMN avis_stage_formation, DROP COLUMN avis_stage_sgdf, DROP COLUMN stagiaire_bafd;
--rollback ALTER TABLE campement.sejour DROP COLUMN module_stages_pratiques_actif;
