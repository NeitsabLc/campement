--liquibase formatted sql

--changeset campement:V032-audit-mouvements-stock
--comment: Trace les modifications, annulations et suppressions des mouvements de stock

ALTER TABLE campement.mouvement_stock
    ADD COLUMN annule_at TIMESTAMPTZ NULL,
    ADD COLUMN annule_par_id UUID NULL,
    ADD COLUMN motif_annulation TEXT NULL,
    ADD CONSTRAINT fk_mouvement_stock_annule_par
        FOREIGN KEY (annule_par_id)
            REFERENCES campement.utilisateur (id)
            ON DELETE SET NULL,
    ADD CONSTRAINT chk_mouvement_stock_annulation
        CHECK (
            (annule_at IS NULL AND annule_par_id IS NULL AND motif_annulation IS NULL)
            OR
            (annule_at IS NOT NULL AND motif_annulation IS NOT NULL AND BTRIM(motif_annulation) <> '')
        );

CREATE INDEX idx_mouvement_stock_annule
    ON campement.mouvement_stock (annule_at);

CREATE INDEX idx_mouvement_stock_annule_par
    ON campement.mouvement_stock (annule_par_id);

CREATE TABLE campement.audit_mouvement_stock
(
    id                    UUID         NOT NULL DEFAULT uuidv7(),
    mouvement_stock_id    UUID         NOT NULL,
    sejour_id             UUID         NOT NULL,
    utilisateur_id        UUID,
    utilisateur_libelle   VARCHAR(320) NOT NULL,
    action                VARCHAR(20)  NOT NULL,
    motif                 TEXT         NOT NULL,
    etat_avant            JSONB,
    etat_apres            JSONB,
    created_at            TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_audit_mouvement_stock PRIMARY KEY (id),
    CONSTRAINT fk_audit_mouvement_stock_sejour
        FOREIGN KEY (sejour_id)
            REFERENCES campement.sejour (id)
            ON DELETE RESTRICT,
    CONSTRAINT fk_audit_mouvement_stock_utilisateur
        FOREIGN KEY (utilisateur_id)
            REFERENCES campement.utilisateur (id)
            ON DELETE SET NULL,
    CONSTRAINT chk_audit_mouvement_stock_action
        CHECK (action IN ('MODIFICATION', 'ANNULATION', 'SUPPRESSION')),
    CONSTRAINT chk_audit_mouvement_stock_motif
        CHECK (BTRIM(motif) <> ''),
    CONSTRAINT chk_audit_mouvement_stock_etats
        CHECK (
            (action IN ('MODIFICATION', 'ANNULATION') AND etat_avant IS NOT NULL AND etat_apres IS NOT NULL)
            OR
            (action = 'SUPPRESSION' AND etat_avant IS NOT NULL AND etat_apres IS NULL)
        )
);

CREATE INDEX idx_audit_mouvement_stock_mouvement
    ON campement.audit_mouvement_stock (mouvement_stock_id);

CREATE INDEX idx_audit_mouvement_stock_sejour_date
    ON campement.audit_mouvement_stock (sejour_id, created_at DESC);

CREATE INDEX idx_audit_mouvement_stock_utilisateur
    ON campement.audit_mouvement_stock (utilisateur_id);

--rollback DROP TABLE campement.audit_mouvement_stock; DROP INDEX campement.idx_mouvement_stock_annule_par; DROP INDEX campement.idx_mouvement_stock_annule; ALTER TABLE campement.mouvement_stock DROP CONSTRAINT chk_mouvement_stock_annulation, DROP CONSTRAINT fk_mouvement_stock_annule_par, DROP COLUMN motif_annulation, DROP COLUMN annule_par_id, DROP COLUMN annule_at;
