--liquibase formatted sql

--changeset campement:V011-supprimer-colonnes-inutilisees splitStatements:true endDelimiter:;
--comment: Suppression des colonnes sans usage applicatif et des données redondantes

ALTER TABLE campement.groupe
    DROP COLUMN commentaire;

ALTER TABLE campement.menu
    DROP COLUMN commentaire;

ALTER TABLE campement.mouvement_stock
    DROP COLUMN reference_document,
    DROP COLUMN commentaire;

ALTER TABLE campement.unite
    DROP CONSTRAINT chk_unite_conversion,
    DROP COLUMN facteur_conversion;

ALTER TABLE campement.denree_fournisseur
    DROP COLUMN designation;

--rollback ALTER TABLE campement.denree_fournisseur ADD COLUMN designation VARCHAR(200);
--rollback ALTER TABLE campement.unite ADD COLUMN facteur_conversion NUMERIC(12,6) NOT NULL DEFAULT 1, ADD CONSTRAINT chk_unite_conversion CHECK (facteur_conversion > 0);
--rollback ALTER TABLE campement.mouvement_stock ADD COLUMN reference_document VARCHAR(100), ADD COLUMN commentaire TEXT;
--rollback ALTER TABLE campement.menu ADD COLUMN commentaire TEXT;
--rollback ALTER TABLE campement.groupe ADD COLUMN commentaire TEXT;
