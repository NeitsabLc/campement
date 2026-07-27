--liquibase formatted sql

--changeset blecaer:D003 context:dev splitStatements:true endDelimiter:;
--comment: Initialisation des unités et des denrées

/*
Hypothèses de normalisation :
- les masses sont stockées en grammes ;
- les volumes sont stockés en millilitres ;
- les longueurs connues sont stockées en mètres ;
- les présentations sans contenu mesurable connu (pot, boîte, sachet, 4/4, etc.)
  sont stockées en pièces ;
- les tailles commerciales restent à modéliser dans
  denree_fournisseur_conditionnement ;
- le doublon Pesto (900 g / 1 kg) est fusionné en une seule denrée.
*/

INSERT INTO campement.unite (nom, symbole, facteur_conversion)
VALUES
    ('gramme',      'g',  1),
    ('kilogramme',  'kg', 1000),
    ('millilitre',  'mL', 1),
    ('centilitre',  'cL', 10),
    ('litre',       'L',  1000),
    ('pièce',       'pc', 1),
    ('mètre',       'm',  1)
    ON CONFLICT DO NOTHING;

-- Sirop Jus de fruit
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Jus de pomme brique le comptoir', 'millilitre'), -- source : 1 L
          ('Jus de pomme brique plein fruit', 'millilitre'), -- source : 1 L
          ('Jus d''orange brique le comptoir', 'millilitre'), -- source : 1 L
          ('Sirop de Citron', 'millilitre'), -- source : 1 L
          ('Sirop de fraise Rioba', 'millilitre'), -- source : 1 L
          ('Sirop de grenadine marque repère', 'millilitre'), -- source : 75 cL
          ('Sirop de grenadine rioba', 'millilitre'), -- source : 1 L
          ('Sirop de Menthe auchan', 'millilitre'), -- source : 1L
          ('Sirop de Menthe marque repère', 'millilitre'), -- source : 75 cL
          ('Sirop de Menthe rioba', 'millilitre') -- source : 1 L
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Produits laitiers et frais
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Beurre demi-sel', 'gramme'), -- source : 250 g
          ('Beurre doux', 'gramme'), -- source : 250 g
          ('Brie', 'gramme'), -- source : kg
          ('Camembert', 'pièce'), -- source : pièce
          ('Cheddar', 'pièce'), -- source : paquet
          ('Chèvre', 'pièce'), -- source : pièce
          ('Compote', 'pièce'), -- source : pièce
          ('Comté', 'gramme'), -- source : kg
          ('Crème chocolat', 'pièce'), -- source : pièce
          ('Crème chocolat SL', 'pièce'), -- source : pièce
          ('Crème épaisse', 'millilitre'), -- source : 1 L
          ('Crème liquide', 'millilitre'), -- source : 1 L
          ('Crème vanille', 'pièce'), -- source : pièce
          ('Emmental', 'gramme'), -- source : kg
          ('Emmental râpé', 'gramme'), -- source : kg
          ('Fêta', 'gramme'), -- source : kg
          ('Flamby', 'pièce'), -- source : pièce
          ('Fromage à tartiflette', 'pièce'), -- source : pièce
          ('Fromage blanc', 'gramme'), -- source : kg
          ('Ile flottante', 'pièce'), -- source : pièce
          ('Lait UHU', 'millilitre'), -- source : 1L
          ('Liégeois', 'pièce'), -- source : pièce
          ('Mousse chocolat au lait', 'pièce'), -- source : pièce
          ('Mousse chocolat noir', 'pièce'), -- source : pièce
          ('Mozzarella', 'pièce'), -- source : pièce
          ('Parmesan', 'gramme'), -- source : kg
          ('Pesto', 'gramme'), -- source : 900 g
          ('Petits suisses', 'pièce'), -- source : pièce
          ('Pousses de soja', 'gramme'), -- source : unité absente dans la source
          ('Ravioles', 'gramme'), -- source : kg
          ('Riz au lait', 'pièce'), -- source : pièce
          ('Roquefort', 'gramme'), -- source : unité absente dans la source
          ('Saint Moret', 'pièce'), -- source : barquette
          ('Saint Paulin', 'gramme'), -- source : kg
          ('Taboulé', 'gramme'), -- source : 2,5 kg
          ('Tomme blanche', 'gramme'), -- source : kg
          ('Vache qui rit', 'pièce'), -- source : pièce
          ('Yaourt aux fruits', 'pièce'), -- source : pièce
          ('Yaourt nature', 'pièce'), -- source : pièce
          ('Yaourt végétal', 'pièce') -- source : pièce
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Velouté soupe
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Gaspacho', 'millilitre'), -- source : 1 L
          ('Velouté asperge', 'millilitre'), -- source : 1 L
          ('Velouté de carottes', 'millilitre'), -- source : 1 L
          ('Velouté de champignons', 'millilitre'), -- source : 1 L
          ('Velouté de légumes variés', 'millilitre'), -- source : 1 L
          ('Velouté de légumes vert', 'millilitre'), -- source : 1 L
          ('Velouté de poireaux', 'millilitre'), -- source : 1 L
          ('Velouté de potiron', 'millilitre'), -- source : 1 L
          ('Velouté tomate', 'millilitre') -- source : 1 L
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Goûter
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Barre de céréale', 'pièce'), -- source : pièce
          ('Beurre de cacahuète', 'pièce'), -- source : pot
          ('Biscuit BN', 'pièce'), -- source : pièce
          ('Brioche', 'pièce'), -- source : pièce
          ('Brownie', 'pièce'), -- source : pièce
          ('Chamallow', 'pièce'), -- source : sachet
          ('Chocolat au lait', 'pièce'), -- source : tablette
          ('Chocolat noir', 'pièce'), -- source : tablette
          ('Confiture abricot le berger des fruits', 'gramme'), -- source : 1kg
          ('Confiture fraise le berger des fruits grand', 'gramme'), -- source : 1 kg
          ('Confiture fraise le berger des fruits petits', 'gramme'), -- source : 500 g
          ('Conserve de pomme', 'pièce'), -- source : 04-avr
          ('Crème de marrons', 'pièce'), -- source : pot
          ('Madeleine', 'pièce'), -- source : barquette
          ('Marbré', 'pièce'), -- source : pièce
          ('Mont blanc vanille', 'gramme'), -- source : kg ?
          ('Pain d''épice', 'pièce'), -- source : pièce
          ('Palet breton', 'pièce'), -- source : pièce
          ('Petit beurre', 'pièce'), -- source : boite
          ('Quatre quart', 'pièce'), -- source : pièce
          ('Speculoos concassés', 'gramme') -- source : 1,1 kg
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Épicerie sucrée
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Café', 'pièce'), -- source : pots
          ('Chocolat poudre', 'gramme'), -- source : kg
          ('Farine', 'gramme'), -- source : 1 KG
          ('Levure biologique', 'pièce'), -- source : sachets
          ('Levure chimique', 'pièce'), -- source : sachets
          ('Miel', 'gramme'), -- source : 500 g
          ('Nesquik', 'gramme'), -- source : 1 kg
          ('Sucre morceaux', 'gramme'), -- source : kg
          ('Sucre poudre', 'gramme'), -- source : 1kg
          ('Sucre vanillé', 'pièce'), -- source : sachets
          ('Thé', 'pièce') -- source : boîte
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Epicerie salée
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Bouillon de légumes', 'gramme'), -- source : 1 kg
          ('Chips', 'pièce'), -- source : sachet
          ('Cornichon', 'gramme'), -- source : 430g
          ('Coulis tomate brique', 'millilitre'), -- source : 1 L
          ('Huile de tournesol', 'millilitre'), -- source : 1L
          ('Huile d''olive', 'millilitre'), -- source : 1L
          ('Jus de citron', 'millilitre'), -- source : L
          ('Ketchup', 'gramme'), -- source : 280g
          ('Mayonnaise', 'pièce'), -- source : tube
          ('Moutarde', 'gramme'), -- source : 265g
          ('Vinaigre balsamique', 'millilitre'), -- source : 1 L
          ('Vinaigre de cidre', 'millilitre'), -- source : 1 L
          ('Vinaigre de vin', 'millilitre') -- source : 1 L
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Féculent
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Blé', 'gramme'), -- source : kg
          ('Chapelure', 'gramme'), -- source : kg
          ('Chapelure blanche', 'gramme'), -- source : kg
          ('Chapelure brune', 'gramme'), -- source : kg
          ('Couscous', 'gramme'), -- source : kg
          ('Crozet', 'gramme'), -- source : kg
          ('Fusili', 'gramme'), -- source : kg
          ('Lentilles corail', 'gramme'), -- source : kg
          ('Lentilles vertes', 'gramme'), -- source : kg
          ('Macaroni', 'gramme'), -- source : kg
          ('Noddle dried', 'pièce'), -- source : sachet
          ('Nouille de riz', 'gramme'), -- source : kg
          ('Pain burger', 'pièce'), -- source : pièce
          ('Pain de mie', 'pièce'), -- source : paquet
          ('Pois chiche sec', 'gramme'), -- source : kg
          ('Polenta', 'gramme'), -- source : kg
          ('Quinoa', 'gramme'), -- source : kg
          ('Riz basmati', 'gramme'), -- source : kg
          ('Riz étuve', 'gramme'), -- source : kg
          ('Riz incollable', 'gramme'), -- source : kg
          ('Riz rond', 'gramme'), -- source : kg
          ('Semoule de blé fine', 'gramme'), -- source : kg
          ('Semoule de riz', 'gramme'), -- source : kg
          ('Spaetzle', 'gramme'), -- source : kg
          ('Spaghetti', 'gramme'), -- source : kg
          ('Tagliatelle', 'gramme'), -- source : kg
          ('Tortilla', 'pièce') -- source : pièce
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Conserves et sous vides
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Betterave conserves', 'pièce'), -- source : 4/4
          ('Betteraves rouges épluchées sous vide', 'gramme'), -- source : 500 g
          ('Cacahuète', 'gramme'), -- source : kg
          ('Câpres', 'pièce'), -- source : bocal
          ('Cerneaux de noix', 'gramme'), -- source : kg
          ('Concentré de tomates', 'gramme'), -- source : 475g
          ('Coulis de tomate conserve', 'gramme'), -- source : 2,5 kg
          ('Crème de coco', 'millilitre'), -- source : 1L
          ('Crouton', 'gramme'), -- source : kg
          ('Haricots rouges', 'gramme'), -- source : 500g
          ('Haricots verts', 'pièce'), -- source : 4/4
          ('Haricots verts petits', 'pièce'), -- source : 1/2
          ('Houmous', 'gramme'), -- source : kg
          ('Lait d''amande', 'millilitre'), -- source : 1 L
          ('Lait de coco', 'pièce'), -- source : 1/2
          ('Lait de soja', 'millilitre'), -- source : 1 L
          ('Légumes pour coucous 4/4', 'pièce'), -- source : 4/4
          ('Légumes pour coucous 5/1', 'pièce'), -- source : 5/1
          ('Lentille verte conserve', 'pièce'), -- source : 5/1
          ('Maïs', 'gramme'), -- source : 570g
          ('Noix de cajou', 'gramme'), -- source : kg
          ('Olives noires dénoyautées', 'gramme'), -- source : 360 g
          ('Pâté de campagne', 'pièce'), -- source : pièce
          ('Pâté de volaille', 'pièce'), -- source : pièce
          ('Petit pois', 'pièce'), -- source : 4/4
          ('Petit pois carotte', 'pièce'), -- source : 4/4
          ('Pois chiche', 'gramme'), -- source : 530g
          ('Pulpe de tomate', 'gramme'), -- source : 765g
          ('Ratatouille provencale', 'pièce'), -- source : 4/4
          ('Ratatouille 5/1', 'pièce'), -- source : 5/1
          ('Saladières', 'pièce'), -- source : pièce
          ('Thon', 'pièce'), -- source : 4/4
          ('Tomates entières pelées', 'gramme') -- source : 570g
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Fruits et légumes frais
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Abricot', 'pièce'), -- source : pièce
          ('Ail', 'gramme'), -- source : kg
          ('Ananas', 'pièce'), -- source : pièce
          ('Banane', 'pièce'), -- source : pièce
          ('Betterave', 'gramme'), -- source : kg
          ('Carotte', 'gramme'), -- source : kg
          ('Champignon', 'gramme'), -- source : kg
          ('Chou fleur', 'gramme'), -- source : kg
          ('Chou rouge', 'gramme'), -- source : kg
          ('Citron jaune', 'gramme'), -- source : kg
          ('Citron vert', 'gramme'), -- source : kg
          ('Clémentines', 'gramme'), -- source : kg
          ('Concombre', 'pièce'), -- source : pièce
          ('Courgette', 'gramme'), -- source : kg
          ('Echalotte', 'pièce'), -- source : pièce
          ('Epinards', 'gramme'), -- source : kg
          ('Fraise', 'gramme'), -- source : kg
          ('Mâche', 'gramme'), -- source : kg
          ('Melon', 'pièce'), -- source : pièce
          ('Navet', 'gramme'), -- source : kg
          ('Nectarine jaune', 'pièce'), -- source : pièce
          ('Oignons', 'gramme'), -- source : kg
          ('Orange', 'gramme'), -- source : kg
          ('Pastèque', 'pièce'), -- source : pièce
          ('Patate douce', 'gramme'), -- source : kg
          ('Pêche blanche', 'pièce'), -- source : pièce
          ('Pêche jaune', 'pièce'), -- source : pièce
          ('Persil', 'pièce'), -- source : pièce
          ('Poire', 'pièce'), -- source : pièce
          ('Poivron jaune', 'pièce'), -- source : pièce
          ('Poivron orange', 'pièce'), -- source : pièce
          ('Poivron rouge', 'pièce'), -- source : pièce
          ('Pomelos', 'pièce'), -- source : pièce
          ('Pomme', 'pièce'), -- source : pièce
          ('Pomme de terre', 'gramme'), -- source : kg
          ('Pomme de terre précuite', 'gramme'), -- source : kg
          ('Prunes', 'gramme'), -- source : kg
          ('Radis noir', 'gramme'), -- source : kg
          ('Raisin', 'gramme'), -- source : kg
          ('Salade', 'pièce'), -- source : pièce
          ('Tomates', 'gramme') -- source : kg
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Poisson et viande
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Blanc de poulet', 'gramme'), -- source : kg
          ('Filet de dinde', 'gramme'), -- source : kg
          ('Jambon', 'pièce'), -- source : tranche
          ('Jambon cru de pays', 'pièce'), -- source : tranche
          ('Merguez', 'pièce'), -- source : pièce
          ('Œufs', 'pièce'), -- source : pièce
          ('Saucisses', 'pièce'), -- source : pièce
          ('Saucisson', 'gramme'), -- source : kg
          ('Viande hachée', 'gramme'), -- source : kg
          ('Lardons', 'gramme') -- source : kg
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Alternatives Végé
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Aiguillette de blé', 'gramme'), -- source : kg
          ('Emincé de blé', 'gramme'), -- source : kg
          ('Hâché végétal', 'gramme'), -- source : kg
          ('Nugget Blé', 'gramme'), -- source : kg
          ('Saucisse végé', 'gramme') -- source : kg
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Sans gluten
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Cookies sans gluten', 'pièce'), -- source : boîte
          ('Gâteau fourré sans gluten', 'pièce'), -- source : boîte
          ('Madeleine sans gluten', 'pièce'), -- source : pièce
          ('Pain sans gluten', 'gramme'), -- source : 350g
          ('Spaghetti sans gluten', 'pièce') -- source : boîte
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Congelés
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Baguette pain', 'pièce'), -- source : pièce
          ('Brocoli métro chef', 'gramme'), -- source : kg
          ('Colin', 'gramme'), -- source : kg
          ('Cookie surgelé', 'pièce'), -- source : pièce
          ('Cordon bleu', 'gramme'), -- source : kg
          ('Cordon bleu de dinde', 'gramme'), -- source : kg
          ('Cordon bleu végé tellement bon', 'gramme'), -- source : kg
          ('Egrené de pois bio', 'gramme'), -- source : kg
          ('Falafel fève menthe', 'gramme'), -- source : kg
          ('Feuilleté fromage surg', 'pièce'), -- source : sachet
          ('Filet de poulet sans peau', 'gramme'), -- source : kg
          ('Flan surgelé', 'pièce'), -- source : pièce
          ('Frites', 'gramme'), -- source : kg
          ('Gâteau chocolat surg', 'pièce'), -- source : pièce
          ('Glace', 'pièce'), -- source : pièce
          ('Gnocchi', 'gramme'), -- source : kg
          ('Lanières émincées de poulet rôti', 'gramme'), -- source : kg
          ('Lardons surg', 'gramme'), -- source : kg
          ('Lieu', 'gramme'), -- source : kg
          ('Merguez de bœufs surg', 'gramme'), -- source : kg
          ('Moelleux chocolat', 'gramme'), -- source : 900g
          ('Onion ring surg', 'gramme'), -- source : kg
          ('Pain burger surg', 'pièce'), -- source : pièce
          ('Poisson pané', 'gramme'), -- source : kg
          ('Poulet surg', 'gramme'), -- source : kg
          ('Saucisse Jean Flach', 'gramme'), -- source : kg
          ('Saucisse végétale', 'gramme'), -- source : kg
          ('Saucisses surg', 'gramme'), -- source : kg
          ('Sorbet citron', 'pièce'), -- source : boîte
          ('Sorbet framboise', 'pièce'), -- source : boîte
          ('Steak haché', 'gramme'), -- source : kg
          ('Tarte myrtille', 'pièce'), -- source : pièce
          ('Viande hachée surg', 'gramme') -- source : Kg
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Divers (hygiène)
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Bobine à dévidage centrale', 'pièce'), -- source : pièce
          ('Bobine essuie tout', 'pièce'), -- source : bobine
          ('Détartrant neutre', 'millilitre'), -- source : dose 20 ml
          ('Eau de javel', 'millilitre'), -- source : bidon 5l
          ('Eponge', 'pièce'), -- source : pièce
          ('Film étirable', 'mètre'), -- source : rouleau 3m
          ('Gel lavant main', 'millilitre'), -- source : bidon 1 l
          ('Grattoir métalllique/ maille', 'pièce'), -- source : pièce
          ('Lavette bleue', 'pièce'), -- source : unité
          ('Lingettes désinfectantes', 'pièce'), -- source : boite
          ('Liquide vaisselle main', 'millilitre'), -- source : bidon 5l
          ('Nettoyant détergeant désinfectant', 'millilitre'), -- source : bidon 5l
          ('Nettoyant multi usage', 'millilitre'), -- source : dose 20 ml
          ('Spontex vert', 'mètre') -- source : bobine 5 m
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Autre
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Bicarbonate de soude alimentaire', 'gramme'), -- source : 500 g
          ('Colorant alimentaire', 'pièce'), -- source : boîte
          ('Pique brochette', 'pièce') -- source : pièce
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;

-- Epices
INSERT INTO campement.denree (sejour_id, nom, unite_reference_id)
SELECT sejour.id, donnees.nom, unite.id
FROM (VALUES
          ('Canelle grand', 'pièce'), -- source : pots
          ('Canelle petit', 'pièce'), -- source : pots
          ('Ciboulette', 'gramme'), -- source : kg
          ('Cumin grand', 'pièce'), -- source : pots
          ('Cumin petit', 'pièce'), -- source : pots
          ('Cumin recharge', 'gramme'), -- source : kg
          ('Curry grand', 'pièce'), -- source : pots
          ('Curry petit', 'pièce'), -- source : pots
          ('Curry recharge', 'gramme'), -- source : kg
          ('Epice chili', 'pièce'), -- source : pots
          ('Gingembre', 'pièce'), -- source : pots
          ('Gros sel grand', 'pièce'), -- source : pots
          ('Gros sel petit', 'pièce'), -- source : pots
          ('Gros sel recharge', 'gramme'), -- source : kg
          ('Herbes de provence grand', 'pièce'), -- source : pots
          ('Herbes de provence petit', 'pièce'), -- source : pots
          ('Herbes de provence recharge', 'gramme'), -- source : kg
          ('Muscade', 'pièce'), -- source : pots
          ('Origan', 'gramme'), -- source : kg
          ('Paprika', 'gramme'), -- source : kg
          ('Persil hâché', 'gramme'), -- source : kg
          ('Piment fort', 'gramme'), -- source : kg
          ('Poivre grand', 'pièce'), -- source : pots
          ('Poivre petit', 'pièce'), -- source : pots
          ('Poivre recharge', 'gramme'), -- source : kg
          ('Quatre épice petit', 'pièce'), -- source : pots
          ('Sel fin grand', 'pièce'), -- source : pots
          ('Sel fin petit', 'pièce'), -- source : pots
          ('Sel fin recharge', 'gramme') -- source : kg
     ) AS donnees(nom, unite_nom)
         JOIN campement.unite AS unite
              ON unite.nom = donnees.unite_nom
CROSS JOIN campement.sejour AS sejour
WHERE sejour.nom = 'Test séjour'
    ON CONFLICT (sejour_id, nom) DO NOTHING;
