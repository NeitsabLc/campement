--liquibase formatted sql

--changeset campement:V003-referentiel-origines-mouvement splitStatements:true endDelimiter:;
--comment: Ajoute les origines de mouvement de stock utilisées par l'application aux environnements sans données de développement

INSERT INTO campement.origine_mouvement (code, libelle, ordre)
SELECT donnees.code, donnees.libelle, donnees.ordre
FROM (VALUES
    ('FOURNISSEUR',        'Livraison fournisseur', 1),
    ('DISTRIBUTION',       'Distribution',          2),
    ('INVENTAIRE',         'Inventaire',            3),
    ('POUBELLE',           'Mise au rebut',         4),
    ('RETOUR_ALIMENTAIRE', 'Retour alimentaire',    5),
    ('CORRECTION',         'Correction manuelle',   6)
) AS donnees(code, libelle, ordre)
ON CONFLICT (code) DO NOTHING;
