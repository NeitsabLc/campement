--liquibase formatted sql

--changeset campement:V028-ajouter-origine-donation splitStatements:true endDelimiter:;
--comment: Ajoute la donation aux origines possibles des sorties de stock

INSERT INTO campement.origine_mouvement (code, libelle, ordre)
VALUES ('DONATION', 'Donation', 7)
ON CONFLICT (code) DO UPDATE
SET libelle = EXCLUDED.libelle,
    ordre = EXCLUDED.ordre,
    actif = TRUE,
    updated_at = CURRENT_TIMESTAMP;

--rollback DELETE FROM campement.origine_mouvement WHERE code = 'DONATION';
