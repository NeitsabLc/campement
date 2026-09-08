--liquibase formatted sql

--changeset campement:D001-jeu-donnees-developpement splitStatements:true endDelimiter:;
--comment: Jeu de démonstration autonome pour le profil dev

INSERT INTO campement.sejour (nom, date_debut, date_fin, lieu)
VALUES ('Séjour de développement', DATE '2026-07-01', DATE '2026-07-31', 'Campement local')
ON CONFLICT DO NOTHING;

INSERT INTO campement.sejour_public_cible (sejour_id, public_cible_id)
SELECT sejour.id, public_cible.id
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

UPDATE campement.sejour
SET module_intendance_actif = TRUE,
    module_administratif_actif = TRUE,
    module_situations_particulieres_actif = TRUE,
    actif = TRUE
WHERE nom = 'Séjour de développement'
  AND date_debut = DATE '2026-07-01'
  AND date_fin = DATE '2026-07-31';

INSERT INTO campement.participant (
    groupe_id,
    type,
    nom,
    prenom,
    date_naissance,
    telephone_parent_1,
    email_parents,
    date_debut_presence,
    date_fin_presence
)
SELECT groupe.id,
       'jeune',
       'Fixture',
       'Camille',
       DATE '2013-05-12',
       '0600000000',
       'responsables-fixture@example.test',
       DATE '2026-07-01',
       DATE '2026-07-31'
FROM campement.groupe AS groupe
JOIN campement.sejour AS sejour ON sejour.id = groupe.sejour_id
WHERE groupe.nom = 'Groupe de développement'
  AND sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
  AND sejour.date_fin = DATE '2026-07-31'
  AND NOT EXISTS (
      SELECT 1
      FROM campement.participant AS participant
      WHERE participant.groupe_id = groupe.id
        AND participant.nom = 'Fixture'
        AND participant.prenom = 'Camille'
        AND participant.email_parents = 'responsables-fixture@example.test'
  );

-- Ce changeset complète les fixtures D001/D002/D005 sans réécrire leur historique.
-- Il peut donc être appliqué aussi bien à une base locale existante qu'à une base neuve.

UPDATE campement.sejour
SET date_fin = DATE '2026-07-05',
    lieu = 'Base scoute de Mélan',
    module_intendance_actif = TRUE,
    module_administratif_actif = TRUE,
    module_situations_particulieres_actif = TRUE,
    actif = TRUE
WHERE nom = 'Séjour de développement'
  AND date_debut = DATE '2026-07-01';

UPDATE campement.groupe AS groupe
SET nom = 'Unité Scouts-Guides',
    effectif_jeune = 24,
    effectif_adulte = 4,
    type = 'scouts-guides',
    date_debut_presence = DATE '2026-07-01',
    date_fin_presence = DATE '2026-07-05'
FROM campement.sejour AS sejour
WHERE groupe.sejour_id = sejour.id
  AND sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
  AND groupe.nom = 'Groupe de développement';

UPDATE campement.groupe AS groupe
SET date_debut_presence = DATE '2026-07-01',
    date_fin_presence = DATE '2026-07-05'
FROM campement.sejour AS sejour
WHERE groupe.sejour_id = sejour.id
  AND sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01';

UPDATE campement.participant AS participant
SET date_debut_presence = DATE '2026-07-01',
    date_fin_presence = DATE '2026-07-05'
FROM campement.groupe AS groupe
JOIN campement.sejour AS sejour ON sejour.id = groupe.sejour_id
WHERE participant.groupe_id = groupe.id
  AND sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01';

INSERT INTO campement.groupe (
    sejour_id, nom, effectif_jeune, effectif_adulte, type,
    date_debut_presence, date_fin_presence
)
SELECT sejour.id,
       donnees.nom,
       donnees.effectif_jeune,
       donnees.effectif_adulte,
       donnees.type,
       DATE '2026-07-01',
       DATE '2026-07-05'
FROM (VALUES
    ('Ronde des Lucioles', 12, 4, 'farfadets'),
    ('Meute des Écureuils', 18, 4, 'louveteaux-jeannettes'),
    ('Caravane des Sommets', 16, 3, 'pionniers-caravelles'),
    ('Relais des Compagnons', 8, 2, 'compagnons'),
    ('Équipe territoriale', 0, 12, 'adulte')
) AS donnees(nom, effectif_jeune, effectif_adulte, type)
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
ON CONFLICT (sejour_id, nom) DO UPDATE
SET effectif_jeune = EXCLUDED.effectif_jeune,
    effectif_adulte = EXCLUDED.effectif_adulte,
    type = EXCLUDED.type,
    date_debut_presence = EXCLUDED.date_debut_presence,
    date_fin_presence = EXCLUDED.date_fin_presence,
    actif = TRUE;

INSERT INTO campement.fournisseur (sejour_id, nom, telephone, email, adresse)
SELECT sejour.id, donnees.nom, donnees.telephone, donnees.email, donnees.adresse
FROM (VALUES
    ('Métro Alpes', '04 50 00 00 01', 'commandes@metro-alpes.example.test', '12 avenue du Marché, 74000 Annecy'),
    ('Primeur des Vallées', '04 50 00 00 02', 'contact@primeur-vallees.example.test', '8 chemin des Vergers, 74410 Saint-Jorioz'),
    ('BioCoop du Camp', '04 50 00 00 03', 'pro@biocoop-camp.example.test', '3 place des Alpages, 74320 Sévrier')
) AS donnees(nom, telephone, email, adresse)
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
ON CONFLICT (sejour_id, nom) DO UPDATE
SET telephone = EXCLUDED.telephone,
    email = EXCLUDED.email,
    adresse = EXCLUDED.adresse,
    actif = TRUE;

CREATE TEMP TABLE jeu_denrees_dev (
    nom VARCHAR(150) PRIMARY KEY,
    symbole_reference VARCHAR(10) NOT NULL,
    symbole_inventaire VARCHAR(10) NOT NULL,
    fournisseur VARCHAR(150) NOT NULL,
    reference VARCHAR(100) NOT NULL,
    conditionnement_exterieur VARCHAR(50) NOT NULL,
    quantite_exterieure NUMERIC(12,3) NOT NULL,
    conditionnement_commercial VARCHAR(50) NOT NULL,
    quantite_commerciale NUMERIC(12,3) NOT NULL
) ON COMMIT DROP;

INSERT INTO jeu_denrees_dev VALUES
    ('Tomates',             'g',  'barquette', 'Primeur des Vallées', 'PRI-TOM-01', 'carton',  6, 'barquette', 1000),
    ('Pâtes',               'g',  'paquet', 'Métro Alpes',         'MET-PAT-01', 'carton',  8, 'paquet',    1000),
    ('Pain complet',        'pc', 'paquet', 'BioCoop du Camp',     'BIO-PAI-01', 'carton',  8, 'paquet',       1),
    ('Beurre',              'g',  'barquette', 'Métro Alpes',         'MET-BEU-01', 'carton', 20, 'barquette',  250),
    ('Lait demi-écrémé',    'mL', 'brique', 'Métro Alpes',         'MET-LAI-01', 'carton',  6, 'brique',     1000),
    ('Cacao en poudre',     'g',  'boîte', 'BioCoop du Camp',     'BIO-CAC-01', 'carton',  6, 'boîte',       800),
    ('Confiture de fraises','g',  'pot', 'BioCoop du Camp',     'BIO-CON-01', 'carton',  6, 'pot',         750),
    ('Céréales',            'g',  'paquet', 'Métro Alpes',         'MET-CER-01', 'carton', 10, 'paquet',      500),
    ('Pommes',              'g',  'cagette', 'Primeur des Vallées', 'PRI-POM-01', 'palette', 8, 'cagette',    5000),
    ('Carottes',            'g',  'sachet', 'Primeur des Vallées', 'PRI-CAR-01', 'carton', 10, 'sachet',     1000),
    ('Pois chiches',        'g',  'conserve', 'Métro Alpes',         'MET-POI-01', 'carton', 12, 'conserve',    530),
    ('Œufs',                'pc', 'boîte', 'Primeur des Vallées', 'PRI-OEU-01', 'carton', 12, 'boîte',        12),
    ('Farine',              'g',  'paquet', 'BioCoop du Camp',     'BIO-FAR-01', 'carton', 10, 'paquet',     1000),
    ('Salade verte',        'pc', 'cagette', 'Primeur des Vallées', 'PRI-SAL-01', 'palette', 6, 'cagette',      12),
    ('Concombres',          'pc', 'cagette', 'Primeur des Vallées', 'PRI-COC-01', 'palette', 8, 'cagette',      12),
    ('Riz',                 'g',  'sac', 'Métro Alpes',         'MET-RIZ-01', 'palette',10, 'sac',        5000),
    ('Poulet',              'g',  'barquette', 'Métro Alpes',         'MET-POU-01', 'carton',  6, 'barquette', 2000),
    ('Lait de coco',        'mL', 'conserve', 'BioCoop du Camp',     'BIO-LCO-01', 'carton', 12, 'conserve',    400),
    ('Coulis de tomate',    'mL', 'brique', 'Métro Alpes',         'MET-CTO-01', 'carton', 12, 'brique',      500),
    ('Emmental',            'g',  'sachet', 'Métro Alpes',         'MET-EMM-01', 'carton', 10, 'sachet',      500),
    ('Camembert',           'pc', 'boîte', 'BioCoop du Camp',     'BIO-CAM-01', 'carton', 12, 'boîte',         1),
    ('Yaourt nature',       'pc', 'barquette', 'BioCoop du Camp',     'BIO-YAO-01', 'carton',  8, 'barquette',   12),
    ('Chocolat noir',       'g',  'paquet', 'BioCoop du Camp',     'BIO-CHO-01', 'carton', 12, 'paquet',      200),
    ('Bananes',             'g',  'cagette', 'Primeur des Vallées', 'PRI-BAN-01', 'palette', 8, 'cagette',    5000),
    ('Sucre',               'g',  'paquet', 'Métro Alpes',         'MET-SUC-01', 'carton', 10, 'paquet',     1000),
    ('Pommes de terre',     'g',  'sac', 'Primeur des Vallées', 'PRI-PDT-01', 'palette',10, 'sac',       10000),
    ('Thon',                'g',  'conserve', 'Métro Alpes',         'MET-THO-01', 'carton', 12, 'conserve',    800),
    ('Maïs',                'g',  'conserve', 'Métro Alpes',         'MET-MAI-01', 'carton', 12, 'conserve',    570),
    ('Tortillas',           'pc', 'paquet', 'Métro Alpes',         'MET-TOR-01', 'carton',  8, 'paquet',       12),
    ('Compote de pommes',   'pc', 'barquette', 'BioCoop du Camp',     'BIO-COM-01', 'carton',  8, 'barquette',   12),
    ('Brioche',             'pc', 'paquet', 'Métro Alpes',         'MET-BRI-01', 'carton',  8, 'paquet',        1),
    ('Jus de pomme',        'mL', 'brique', 'BioCoop du Camp',     'BIO-JUS-01', 'carton',  6, 'brique',     1000);

-- Quelques conditionnements usuels manquaient au référentiel initial.
INSERT INTO campement.unite (nom, symbole, utilisable_conditionnement)
VALUES
    ('cagette', 'cagette', TRUE),
    ('sac', 'sac', TRUE)
ON CONFLICT (nom) DO UPDATE SET utilisable_conditionnement = TRUE;

INSERT INTO campement.denree (
    sejour_id, nom, unite_reference_id, unite_inventaire_id
)
SELECT sejour.id,
       donnees.nom,
       unite_reference.id,
       unite_inventaire.id
FROM jeu_denrees_dev AS donnees
JOIN campement.unite AS unite_reference
  ON unite_reference.symbole = donnees.symbole_reference
JOIN campement.unite AS unite_inventaire
  ON unite_inventaire.symbole = donnees.symbole_inventaire
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
ON CONFLICT (sejour_id, nom) DO UPDATE
SET unite_reference_id = EXCLUDED.unite_reference_id,
    unite_inventaire_id = EXCLUDED.unite_inventaire_id,
    actif = TRUE;

INSERT INTO campement.denree_fournisseur (fournisseur_id, denree_id, reference)
SELECT fournisseur.id, denree.id, donnees.reference
FROM jeu_denrees_dev AS donnees
JOIN campement.sejour AS sejour
  ON sejour.nom = 'Séjour de développement'
 AND sejour.date_debut = DATE '2026-07-01'
JOIN campement.fournisseur AS fournisseur
  ON fournisseur.sejour_id = sejour.id
 AND fournisseur.nom = donnees.fournisseur
JOIN campement.denree AS denree
  ON denree.sejour_id = sejour.id
 AND denree.nom = donnees.nom
ON CONFLICT (fournisseur_id, reference) DO UPDATE
SET denree_id = EXCLUDED.denree_id,
    actif = TRUE;

-- Niveau 1 : contenant logistique (carton ou palette).
INSERT INTO campement.denree_fournisseur_conditionnement (
    reference_fournisseur_id, ordre, libelle, conditionnement_id,
    quantite_contenu, libelle_contenu, unite_contenu_id
)
SELECT reference.id,
       1,
       unite_exterieure.nom,
       unite_exterieure.id,
       donnees.quantite_exterieure,
       unite_commerciale.nom,
       NULL
FROM jeu_denrees_dev AS donnees
JOIN campement.sejour AS sejour
  ON sejour.nom = 'Séjour de développement'
 AND sejour.date_debut = DATE '2026-07-01'
JOIN campement.fournisseur AS fournisseur
  ON fournisseur.sejour_id = sejour.id
 AND fournisseur.nom = donnees.fournisseur
JOIN campement.denree_fournisseur AS reference
  ON reference.fournisseur_id = fournisseur.id
 AND reference.reference = donnees.reference
JOIN campement.unite AS unite_exterieure
  ON unite_exterieure.nom = donnees.conditionnement_exterieur
JOIN campement.unite AS unite_commerciale
  ON unite_commerciale.nom = donnees.conditionnement_commercial
WHERE NOT EXISTS (
    SELECT 1
    FROM campement.denree_fournisseur_conditionnement AS existant
    WHERE existant.reference_fournisseur_id = reference.id
      AND existant.ordre = 1
);

-- Niveau 2 : conditionnement commandable (paquet, brique, conserve, cagette...).
INSERT INTO campement.denree_fournisseur_conditionnement (
    reference_fournisseur_id, ordre, libelle, conditionnement_id,
    quantite_contenu, libelle_contenu, unite_contenu_id
)
SELECT reference.id,
       2,
       unite_commerciale.nom,
       unite_commerciale.id,
       donnees.quantite_commerciale,
       NULL,
       unite_reference.id
FROM jeu_denrees_dev AS donnees
JOIN campement.sejour AS sejour
  ON sejour.nom = 'Séjour de développement'
 AND sejour.date_debut = DATE '2026-07-01'
JOIN campement.fournisseur AS fournisseur
  ON fournisseur.sejour_id = sejour.id
 AND fournisseur.nom = donnees.fournisseur
JOIN campement.denree_fournisseur AS reference
  ON reference.fournisseur_id = fournisseur.id
 AND reference.reference = donnees.reference
JOIN campement.unite AS unite_commerciale
  ON unite_commerciale.nom = donnees.conditionnement_commercial
JOIN campement.unite AS unite_reference
  ON unite_reference.symbole = donnees.symbole_reference
WHERE NOT EXISTS (
    SELECT 1
    FROM campement.denree_fournisseur_conditionnement AS existant
    WHERE existant.reference_fournisseur_id = reference.id
      AND existant.ordre = 2
);

-- Niveau 3 : unité physique terminale nécessaire aux conversions.
INSERT INTO campement.denree_fournisseur_conditionnement (
    reference_fournisseur_id, ordre, libelle, conditionnement_id,
    quantite_contenu, libelle_contenu, unite_contenu_id
)
SELECT reference.id,
       3,
       unite_reference.nom,
       unite_reference.id,
       1,
       NULL,
       unite_reference.id
FROM jeu_denrees_dev AS donnees
JOIN campement.sejour AS sejour
  ON sejour.nom = 'Séjour de développement'
 AND sejour.date_debut = DATE '2026-07-01'
JOIN campement.fournisseur AS fournisseur
  ON fournisseur.sejour_id = sejour.id
 AND fournisseur.nom = donnees.fournisseur
JOIN campement.denree_fournisseur AS reference
  ON reference.fournisseur_id = fournisseur.id
 AND reference.reference = donnees.reference
JOIN campement.unite AS unite_reference
  ON unite_reference.symbole = donnees.symbole_reference
WHERE NOT EXISTS (
    SELECT 1
    FROM campement.denree_fournisseur_conditionnement AS existant
    WHERE existant.reference_fournisseur_id = reference.id
      AND existant.ordre = 3
);

CREATE TEMP TABLE jeu_recettes_dev (
    nom VARCHAR(150) PRIMARY KEY,
    categorie VARCHAR(20) NOT NULL
) ON COMMIT DROP;

INSERT INTO jeu_recettes_dev VALUES
    ('Tartines, confiture et jus', 'PETIT_DEJEUNER'),
    ('Bol cacao-banane', 'PETIT_DEJEUNER'),
    ('Salade de crudités', 'ENTREE'),
    ('Carottes et pois chiches', 'ENTREE'),
    ('Pâtes à la tomate', 'PLAT'),
    ('Curry de poulet au riz', 'PLAT'),
    ('Camembert et pain', 'FROMAGE'),
    ('Emmental sur salade', 'FROMAGE'),
    ('Yaourt aux pommes', 'DESSERT'),
    ('Mousse chocolat-banane', 'DESSERT'),
    ('Brioche et compote', 'GOUTER'),
    ('Cookies au chocolat', 'GOUTER');

INSERT INTO campement.recette (sejour_id, nom, categorie)
SELECT sejour.id, donnees.nom, donnees.categorie
FROM jeu_recettes_dev AS donnees
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
ON CONFLICT (sejour_id, nom) DO UPDATE
SET categorie = EXCLUDED.categorie,
    actif = TRUE;

CREATE TEMP TABLE jeu_recettes_denrees_dev (
    recette VARCHAR(150) NOT NULL,
    denree VARCHAR(150) NOT NULL,
    ordre SMALLINT NOT NULL,
    quantite NUMERIC(12,3) NOT NULL,
    PRIMARY KEY (recette, denree)
) ON COMMIT DROP;

-- Quantités individuelles de référence pour un Scout-Guide.
INSERT INTO jeu_recettes_denrees_dev VALUES
    ('Tartines, confiture et jus', 'Pain complet',          1,   0.200),
    ('Tartines, confiture et jus', 'Beurre',                2,  15.000),
    ('Tartines, confiture et jus', 'Confiture de fraises',  3,  25.000),
    ('Tartines, confiture et jus', 'Jus de pomme',          4, 200.000),
    ('Bol cacao-banane',            'Lait demi-écrémé',      1, 250.000),
    ('Bol cacao-banane',            'Cacao en poudre',       2,  15.000),
    ('Bol cacao-banane',            'Céréales',              3,  60.000),
    ('Bol cacao-banane',            'Bananes',               4, 120.000),
    ('Salade de crudités',          'Tomates',               1,  80.000),
    ('Salade de crudités',          'Concombres',            2,   0.250),
    ('Salade de crudités',          'Salade verte',          3,   0.080),
    ('Salade de crudités',          'Maïs',                  4,  30.000),
    ('Carottes et pois chiches',    'Carottes',              1, 100.000),
    ('Carottes et pois chiches',    'Pois chiches',          2,  50.000),
    ('Carottes et pois chiches',    'Yaourt nature',         3,   0.250),
    ('Pâtes à la tomate',           'Pâtes',                 1, 120.000),
    ('Pâtes à la tomate',           'Coulis de tomate',      2, 100.000),
    ('Pâtes à la tomate',           'Emmental',              3,  25.000),
    ('Curry de poulet au riz',      'Poulet',                1, 130.000),
    ('Curry de poulet au riz',      'Riz',                   2,  90.000),
    ('Curry de poulet au riz',      'Lait de coco',          3,  80.000),
    ('Curry de poulet au riz',      'Carottes',              4,  50.000),
    ('Camembert et pain',           'Camembert',             1,   0.125),
    ('Camembert et pain',           'Pain complet',          2,   0.080),
    ('Emmental sur salade',         'Emmental',              1,  35.000),
    ('Emmental sur salade',         'Salade verte',          2,   0.050),
    ('Yaourt aux pommes',           'Yaourt nature',         1,   1.000),
    ('Yaourt aux pommes',           'Pommes',                2, 100.000),
    ('Mousse chocolat-banane',      'Chocolat noir',         1,  30.000),
    ('Mousse chocolat-banane',      'Œufs',                  2,   0.500),
    ('Mousse chocolat-banane',      'Sucre',                 3,  15.000),
    ('Mousse chocolat-banane',      'Bananes',               4,  60.000),
    ('Brioche et compote',          'Brioche',               1,   0.200),
    ('Brioche et compote',          'Compote de pommes',     2,   1.000),
    ('Brioche et compote',          'Jus de pomme',          3, 150.000),
    ('Cookies au chocolat',         'Farine',                1,  45.000),
    ('Cookies au chocolat',         'Beurre',                2,  25.000),
    ('Cookies au chocolat',         'Sucre',                 3,  20.000),
    ('Cookies au chocolat',         'Chocolat noir',         4,  25.000),
    ('Cookies au chocolat',         'Œufs',                  5,   0.200);

INSERT INTO campement.recette_denree (
    recette_id, denree_id, conditionnement_id, ordre
)
SELECT recette.id, denree.id, denree.unite_reference_id, donnees.ordre
FROM jeu_recettes_denrees_dev AS donnees
JOIN campement.sejour AS sejour
  ON sejour.nom = 'Séjour de développement'
 AND sejour.date_debut = DATE '2026-07-01'
JOIN campement.recette AS recette
  ON recette.sejour_id = sejour.id
 AND recette.nom = donnees.recette
JOIN campement.denree AS denree
  ON denree.sejour_id = sejour.id
 AND denree.nom = donnees.denree
WHERE NOT EXISTS (
    SELECT 1
    FROM campement.recette_denree AS existante
    WHERE existante.recette_id = recette.id
      AND existante.denree_id = denree.id
);

INSERT INTO campement.recette_denree_quantite (
    recette_denree_id, sejour_public_cible_id, quantite_individuelle
)
SELECT ligne.id,
       configuration.id,
       ROUND(donnees.quantite * CASE public.code
           WHEN 'FARFADETS' THEN 0.65
           WHEN 'LOUVETEAUX_JEANNETTES' THEN 0.80
           WHEN 'SCOUTS_GUIDES' THEN 1.00
           WHEN 'PIONNIERS_CARAVELLES' THEN 1.20
           WHEN 'COMPAGNONS' THEN 1.25
           WHEN 'ADULTE' THEN 1.15
           ELSE 1.00
       END, 3)
FROM jeu_recettes_denrees_dev AS donnees
JOIN campement.sejour AS sejour
  ON sejour.nom = 'Séjour de développement'
 AND sejour.date_debut = DATE '2026-07-01'
JOIN campement.recette AS recette
  ON recette.sejour_id = sejour.id
 AND recette.nom = donnees.recette
JOIN campement.denree AS denree
  ON denree.sejour_id = sejour.id
 AND denree.nom = donnees.denree
JOIN campement.recette_denree AS ligne
  ON ligne.recette_id = recette.id
 AND ligne.denree_id = denree.id
JOIN campement.sejour_public_cible AS configuration
  ON configuration.sejour_id = sejour.id
 AND configuration.actif = TRUE
JOIN campement.public_cible AS public
  ON public.id = configuration.public_cible_id
ON CONFLICT (recette_denree_id, sejour_public_cible_id) DO UPDATE
SET quantite_individuelle = EXCLUDED.quantite_individuelle;
-- Les vingt créneaux datés : quatre repas par jour pendant cinq jours.
INSERT INTO campement.menu (sejour_id, sejour_type_repas_id, date_menu, nom)
SELECT sejour.id,
       configuration.id,
       jour::date,
       type_repas.libelle
FROM campement.sejour AS sejour
JOIN campement.sejour_type_repas AS configuration
  ON configuration.sejour_id = sejour.id
 AND configuration.actif = TRUE
JOIN campement.type_repas AS type_repas
  ON type_repas.id = configuration.type_repas_id
CROSS JOIN generate_series(DATE '2026-07-01', DATE '2026-07-05', INTERVAL '1 day') AS jour
WHERE sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
ON CONFLICT DO NOTHING;

-- Les trois repas généraux affichés au-dessus du calendrier.
INSERT INTO campement.menu (sejour_id, special_code, nom)
SELECT sejour.id, donnees.code, donnees.nom
FROM (VALUES
    ('EXPLO', 'Explo'),
    ('PIQUE_NIQUE_1', 'Pique-nique 1'),
    ('PIQUE_NIQUE_2', 'Pique-nique 2')
) AS donnees(code, nom)
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
ON CONFLICT DO NOTHING;

-- Une recette petit-déjeuner/goûter, et une recette de chaque catégorie au
-- déjeuner/dîner. Les deux variantes de chaque catégorie alternent sur le séjour.
WITH recettes_numerotees AS (
    SELECT recette.*,
           ROW_NUMBER() OVER (PARTITION BY recette.categorie ORDER BY recette.nom) AS rang
    FROM campement.recette AS recette
    JOIN campement.sejour AS sejour ON sejour.id = recette.sejour_id
    WHERE sejour.nom = 'Séjour de développement'
      AND sejour.date_debut = DATE '2026-07-01'
      AND recette.nom IN (SELECT nom FROM jeu_recettes_dev)
), menus_normaux AS (
    SELECT menu.id,
           menu.date_menu,
           type_repas.code AS type_repas,
           (menu.date_menu - DATE '2026-07-01')::INTEGER AS numero_jour
    FROM campement.menu AS menu
    JOIN campement.sejour AS sejour ON sejour.id = menu.sejour_id
    JOIN campement.sejour_type_repas AS configuration ON configuration.id = menu.sejour_type_repas_id
    JOIN campement.type_repas AS type_repas ON type_repas.id = configuration.type_repas_id
    WHERE sejour.nom = 'Séjour de développement'
      AND sejour.date_debut = DATE '2026-07-01'
      AND menu.special_code IS NULL
), affectations AS MATERIALIZED (
    SELECT menu.id AS menu_id,
           recette.id AS recette_id,
           NULL::VARCHAR(20) AS categorie_menu,
           uuidv7() AS instance_id
    FROM menus_normaux AS menu
    JOIN recettes_numerotees AS recette
      ON recette.categorie = 'PETIT_DEJEUNER'
     AND recette.rang = 1 + MOD(menu.numero_jour, 2)
    WHERE menu.type_repas = 'PETIT_DEJEUNER'

    UNION ALL

    SELECT menu.id,
           recette.id,
           NULL::VARCHAR(20),
           uuidv7()
    FROM menus_normaux AS menu
    JOIN recettes_numerotees AS recette
      ON recette.categorie = 'GOUTER'
     AND recette.rang = 1 + MOD(menu.numero_jour, 2)
    WHERE menu.type_repas = 'GOUTER'

    UNION ALL

    SELECT menu.id,
           recette.id,
           recette.categorie,
           uuidv7()
    FROM menus_normaux AS menu
    JOIN recettes_numerotees AS recette
      ON recette.categorie IN ('ENTREE', 'PLAT', 'FROMAGE', 'DESSERT')
     AND recette.rang = 1 + MOD(
         menu.numero_jour + CASE WHEN menu.type_repas = 'DINER' THEN 1 ELSE 0 END,
         2
     )
    WHERE menu.type_repas IN ('DEJEUNER', 'DINER')
)
INSERT INTO campement.menu_denree (
    menu_id, denree_id, conditionnement_id, categorie,
    recette_id, recette_instance_id, ordre
)
SELECT affectation.menu_id,
       ligne.denree_id,
       ligne.conditionnement_id,
       affectation.categorie_menu,
       affectation.recette_id,
       affectation.instance_id,
       ligne.ordre
FROM affectations AS affectation
JOIN campement.recette_denree AS ligne
  ON ligne.recette_id = affectation.recette_id
WHERE NOT EXISTS (
    SELECT 1
    FROM campement.menu_denree AS existante
    WHERE existante.menu_id = affectation.menu_id
      AND existante.recette_id = affectation.recette_id
      AND existante.denree_id = ligne.denree_id
      AND existante.categorie IS NOT DISTINCT FROM affectation.categorie_menu
);

-- Recopie des portions des recettes sur toutes les lignes des menus datés.
INSERT INTO campement.menu_denree_quantite (
    menu_denree_id, sejour_public_cible_id, quantite_individuelle
)
SELECT menu_ligne.id,
       recette_quantite.sejour_public_cible_id,
       recette_quantite.quantite_individuelle
FROM campement.menu_denree AS menu_ligne
JOIN campement.menu AS menu ON menu.id = menu_ligne.menu_id
JOIN campement.sejour AS sejour ON sejour.id = menu.sejour_id
JOIN campement.recette_denree AS recette_ligne
  ON recette_ligne.recette_id = menu_ligne.recette_id
 AND recette_ligne.denree_id = menu_ligne.denree_id
 AND recette_ligne.conditionnement_id = menu_ligne.conditionnement_id
JOIN campement.recette_denree_quantite AS recette_quantite
  ON recette_quantite.recette_denree_id = recette_ligne.id
WHERE sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
  AND menu.special_code IS NULL
ON CONFLICT (menu_denree_id, sejour_public_cible_id) DO UPDATE
SET quantite_individuelle = EXCLUDED.quantite_individuelle;

CREATE TEMP TABLE jeu_menus_speciaux_dev (
    special_code VARCHAR(20) NOT NULL,
    denree VARCHAR(150) NOT NULL,
    ordre SMALLINT NOT NULL,
    quantite NUMERIC(12,3) NOT NULL,
    PRIMARY KEY (special_code, denree)
) ON COMMIT DROP;

INSERT INTO jeu_menus_speciaux_dev VALUES
    ('EXPLO',          'Tortillas',         1,   2.000),
    ('EXPLO',          'Thon',              2,  80.000),
    ('EXPLO',          'Maïs',              3,  40.000),
    ('EXPLO',          'Tomates',           4,  60.000),
    ('PIQUE_NIQUE_1',  'Pain complet',      1,   0.400),
    ('PIQUE_NIQUE_1',  'Camembert',         2,   0.250),
    ('PIQUE_NIQUE_1',  'Pommes',            3, 150.000),
    ('PIQUE_NIQUE_1',  'Compote de pommes', 4,   1.000),
    ('PIQUE_NIQUE_2',  'Tortillas',         1,   2.000),
    ('PIQUE_NIQUE_2',  'Poulet',            2, 120.000),
    ('PIQUE_NIQUE_2',  'Concombres',        3,   0.250),
    ('PIQUE_NIQUE_2',  'Bananes',           4, 120.000),
    ('PIQUE_NIQUE_2',  'Jus de pomme',      5, 200.000);

INSERT INTO campement.menu_denree (
    menu_id, denree_id, conditionnement_id, categorie, ordre
)
SELECT menu.id,
       denree.id,
       denree.unite_reference_id,
       NULL,
       donnees.ordre
FROM jeu_menus_speciaux_dev AS donnees
JOIN campement.sejour AS sejour
  ON sejour.nom = 'Séjour de développement'
 AND sejour.date_debut = DATE '2026-07-01'
JOIN campement.menu AS menu
  ON menu.sejour_id = sejour.id
 AND menu.special_code = donnees.special_code
JOIN campement.denree AS denree
  ON denree.sejour_id = sejour.id
 AND denree.nom = donnees.denree
WHERE NOT EXISTS (
    SELECT 1
    FROM campement.menu_denree AS existante
    WHERE existante.menu_id = menu.id
      AND existante.denree_id = denree.id
      AND existante.recette_id IS NULL
);

INSERT INTO campement.menu_denree_quantite (
    menu_denree_id, sejour_public_cible_id, quantite_individuelle
)
SELECT menu_ligne.id,
       configuration.id,
       ROUND(donnees.quantite * CASE public.code
           WHEN 'FARFADETS' THEN 0.65
           WHEN 'LOUVETEAUX_JEANNETTES' THEN 0.80
           WHEN 'SCOUTS_GUIDES' THEN 1.00
           WHEN 'PIONNIERS_CARAVELLES' THEN 1.20
           WHEN 'COMPAGNONS' THEN 1.25
           WHEN 'ADULTE' THEN 1.15
           ELSE 1.00
       END, 3)
FROM jeu_menus_speciaux_dev AS donnees
JOIN campement.sejour AS sejour
  ON sejour.nom = 'Séjour de développement'
 AND sejour.date_debut = DATE '2026-07-01'
JOIN campement.menu AS menu
  ON menu.sejour_id = sejour.id
 AND menu.special_code = donnees.special_code
JOIN campement.denree AS denree
  ON denree.sejour_id = sejour.id
 AND denree.nom = donnees.denree
JOIN campement.menu_denree AS menu_ligne
  ON menu_ligne.menu_id = menu.id
 AND menu_ligne.denree_id = denree.id
 AND menu_ligne.recette_id IS NULL
JOIN campement.sejour_public_cible AS configuration
  ON configuration.sejour_id = sejour.id
 AND configuration.actif = TRUE
JOIN campement.public_cible AS public
  ON public.id = configuration.public_cible_id
ON CONFLICT (menu_denree_id, sejour_public_cible_id) DO UPDATE
SET quantite_individuelle = EXCLUDED.quantite_individuelle;

