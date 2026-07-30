--liquibase formatted sql

--changeset campement:V020-registre-presence
CREATE TABLE campement.presence_participant (
    id UUID NOT NULL DEFAULT uuidv7(), participant_id UUID NOT NULL, date_presence DATE NOT NULL,
    statut VARCHAR(10) NOT NULL, commentaire VARCHAR(500), created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_presence_participant PRIMARY KEY (id),
    CONSTRAINT fk_presence_participant FOREIGN KEY (participant_id) REFERENCES campement.participant(id) ON DELETE CASCADE,
    CONSTRAINT uq_presence_participant_date UNIQUE (participant_id, date_presence),
    CONSTRAINT chk_presence_participant_statut CHECK (statut IN ('absent', 'depart')),
    CONSTRAINT chk_presence_depart_commentaire CHECK (statut <> 'depart' OR commentaire IS NOT NULL)
);
CREATE INDEX idx_presence_participant_date ON campement.presence_participant(date_presence);
--rollback DROP TABLE campement.presence_participant;
