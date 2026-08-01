--liquibase formatted sql

--changeset campement:V023-situations-particulieres
--comment: Ajouter le module de suivi des situations particulières

ALTER TABLE campement.sejour
    ADD COLUMN module_situations_particulieres_actif BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TABLE campement.situation_particuliere (
    id UUID NOT NULL DEFAULT uuidv7(),
    sejour_id UUID NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    date_situation DATE NOT NULL,
    informations_complementaires JSONB NOT NULL DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_situation_particuliere PRIMARY KEY (id),
    CONSTRAINT fk_situation_particuliere_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE,
    CONSTRAINT chk_situation_particuliere_libelle CHECK (btrim(libelle) <> ''),
    CONSTRAINT chk_situation_particuliere_informations CHECK (jsonb_typeof(informations_complementaires) = 'array')
);

CREATE INDEX idx_situation_particuliere_sejour_date
    ON campement.situation_particuliere(sejour_id, date_situation DESC);

CREATE TABLE campement.situation_particuliere_participant (
    situation_particuliere_id UUID NOT NULL,
    participant_id UUID NOT NULL,
    CONSTRAINT pk_situation_particuliere_participant PRIMARY KEY (situation_particuliere_id, participant_id),
    CONSTRAINT fk_situation_particuliere_participant_situation FOREIGN KEY (situation_particuliere_id) REFERENCES campement.situation_particuliere(id) ON DELETE CASCADE,
    CONSTRAINT fk_situation_particuliere_participant_participant FOREIGN KEY (participant_id) REFERENCES campement.participant(id) ON DELETE CASCADE
);

CREATE INDEX idx_situation_particuliere_participant_participant
    ON campement.situation_particuliere_participant(participant_id);

CREATE TABLE campement.tache_situation_particuliere (
    id UUID NOT NULL DEFAULT uuidv7(),
    situation_particuliere_id UUID NOT NULL,
    type_predefini VARCHAR(40),
    libelle_libre VARCHAR(200),
    origine VARCHAR(25) NOT NULL,
    statut VARCHAR(15) NOT NULL DEFAULT 'A_FAIRE',
    date_echeance DATE,
    date_realisation DATE,
    commentaire TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_tache_situation_particuliere PRIMARY KEY (id),
    CONSTRAINT fk_tache_situation_particuliere_situation FOREIGN KEY (situation_particuliere_id) REFERENCES campement.situation_particuliere(id) ON DELETE CASCADE,
    CONSTRAINT uq_tache_situation_particuliere_type UNIQUE (situation_particuliere_id, type_predefini),
    CONSTRAINT chk_tache_situation_particuliere_type CHECK (type_predefini IS NULL OR type_predefini IN ('DECLARATION_ACCIDENT_SGDF', 'DECLARATION_EVENEMENT_GRAVE', 'IP_OU_SIGNALEMENT', 'APPEL_LIGNE_URGENCE')),
    CONSTRAINT chk_tache_situation_particuliere_origine CHECK (origine IN ('AUTOMATIQUE', 'MANUELLE_PREDEFINIE', 'LIBRE')),
    CONSTRAINT chk_tache_situation_particuliere_statut CHECK (statut IN ('A_FAIRE', 'REALISE', 'NON_REQUIS')),
    CONSTRAINT chk_tache_situation_particuliere_libelle CHECK ((type_predefini IS NOT NULL AND libelle_libre IS NULL) OR (type_predefini IS NULL AND origine = 'LIBRE' AND btrim(libelle_libre) <> '')),
    CONSTRAINT chk_tache_situation_particuliere_realisation CHECK ((statut = 'REALISE' AND date_realisation IS NOT NULL) OR (statut <> 'REALISE' AND date_realisation IS NULL))
);

CREATE INDEX idx_tache_situation_particuliere_situation
    ON campement.tache_situation_particuliere(situation_particuliere_id);
CREATE INDEX idx_tache_situation_particuliere_statut_echeance
    ON campement.tache_situation_particuliere(statut, date_echeance);

--rollback DROP TABLE campement.tache_situation_particuliere; DROP TABLE campement.situation_particuliere_participant; DROP TABLE campement.situation_particuliere; ALTER TABLE campement.sejour DROP COLUMN module_situations_particulieres_actif;
