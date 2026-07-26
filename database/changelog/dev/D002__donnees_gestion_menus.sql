--liquibase formatted sql

--changeset blecaer:D002 context:dev splitStatements:true endDelimiter:;
--comment: Données minimales de développement pour l'écran de gestion des menus

INSERT INTO campement.sejour_type_repas (sejour_id, type_repas_id, ordre)
SELECT sejour.id, type_repas.id, type_repas.ordre
FROM campement.sejour AS sejour
CROSS JOIN campement.type_repas AS type_repas
WHERE sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
  AND sejour.date_fin = DATE '2026-07-31'
ON CONFLICT (sejour_id, type_repas_id) DO NOTHING;

INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (
    VALUES
        ('Tomates', 'kg'),
        ('Pâtes', 'g')
) AS donnees(nom, symbole_unite)
JOIN campement.unite AS unite ON unite.symbole = donnees.symbole_unite
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Séjour de développement'
ON CONFLICT (sejour_id, nom) DO NOTHING;
