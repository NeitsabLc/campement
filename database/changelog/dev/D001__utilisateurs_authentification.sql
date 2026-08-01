--liquibase formatted sql

--changeset blecaer:D001 context:dev splitStatements:true endDelimiter:;
--validCheckSum: ANY
--comment: Comptes et associations de développement pour l'authentification

INSERT INTO campement.sejour (nom, date_debut, date_fin, lieu)
VALUES ('Séjour de développement', DATE '2026-07-01', DATE '2026-07-31', 'Campement local')
ON CONFLICT DO NOTHING;

INSERT INTO campement.sejour_public_cible (sejour_id, public_cible_id, ordre)
SELECT sejour.id, public_cible.id, public_cible.ordre
FROM campement.sejour AS sejour
CROSS JOIN campement.public_cible AS public_cible
WHERE sejour.nom = 'Séjour de développement'
ON CONFLICT (sejour_id, public_cible_id) DO NOTHING;

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

INSERT INTO campement.groupe (sejour_id, nom, effectif_jeune, effectif_adulte, type, date_debut_presence, date_fin_presence)
SELECT id, 'Groupe de développement', 20, 4, 'scouts-guides', date_debut, date_fin
FROM campement.sejour
WHERE nom = 'Séjour de développement'
  AND date_debut = DATE '2026-07-01'
  AND date_fin = DATE '2026-07-31'
ON CONFLICT (sejour_id, nom) DO NOTHING;

INSERT INTO campement.utilisateur (email, mot_de_passe, prenom, nom, roles, actif)
VALUES
    (
        'admin@campement.local',
        '$2y$13$YWZ0HKiVWna1iHmWyn9vPertoRmYTMLe2e/HsCXPrNxGBF.0OB6MK',
        'Admin',
        'Campement',
        '["ROLE_ADMIN"]'::JSONB,
        TRUE
    ),
    (
        'gestionnaire@campement.local',
        '$2y$13$YWZ0HKiVWna1iHmWyn9vPertoRmYTMLe2e/HsCXPrNxGBF.0OB6MK',
        'Gestionnaire',
        'Campement',
        '["ROLE_GESTIONNAIRE"]'::JSONB,
        TRUE
    )
ON CONFLICT (email) DO NOTHING;

INSERT INTO campement.utilisateur (groupe_id, email, mot_de_passe, prenom, nom, roles, actif)
SELECT groupe.id,
       'groupe@campement.local',
       '$2y$13$YWZ0HKiVWna1iHmWyn9vPertoRmYTMLe2e/HsCXPrNxGBF.0OB6MK',
       'Groupe',
       'Campement',
       '["ROLE_GROUPE"]'::JSONB,
       TRUE
FROM campement.groupe AS groupe
JOIN campement.sejour AS sejour ON sejour.id = groupe.sejour_id
WHERE groupe.nom = 'Groupe de développement'
  AND sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
  AND sejour.date_fin = DATE '2026-07-31'
ON CONFLICT (email) DO NOTHING;

INSERT INTO campement.utilisateur_sejour (utilisateur_id, sejour_id)
SELECT utilisateur.id, sejour.id
FROM campement.utilisateur AS utilisateur
CROSS JOIN campement.sejour AS sejour
WHERE utilisateur.email = 'gestionnaire@campement.local'
  AND sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
  AND sejour.date_fin = DATE '2026-07-31'
ON CONFLICT (utilisateur_id, sejour_id) DO NOTHING;
