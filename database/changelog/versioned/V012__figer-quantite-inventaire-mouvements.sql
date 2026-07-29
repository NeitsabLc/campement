--liquibase formatted sql

--changeset campement:V012-figer-quantite-inventaire-mouvements splitStatements:true endDelimiter:;
--comment: Fige la quantité d'inventaire des mouvements pour préserver l'historique lors d'un changement de conditionnement

ALTER TABLE campement.mouvement_stock_ligne
    ADD COLUMN quantite_unite_inventaire NUMERIC(12,3);

-- Lorsqu'une entrée a été directement saisie dans l'unité d'inventaire
-- (par exemple en cartons), sa quantité historique est encore disponible
-- dans le détail des conditionnements et doit être privilégiée.
UPDATE campement.mouvement_stock_ligne ligne
SET quantite_unite_inventaire = historique.quantite
FROM (
    SELECT detail.mouvement_stock_ligne_id, SUM(detail.quantite) AS quantite
    FROM campement.mouvement_stock_ligne_conditionnement detail
    JOIN campement.denree_fournisseur_conditionnement conditionnement
      ON conditionnement.id = detail.conditionnement_id
    JOIN campement.mouvement_stock_ligne mouvement_ligne
      ON mouvement_ligne.id = detail.mouvement_stock_ligne_id
    JOIN campement.denree denree ON denree.id = mouvement_ligne.denree_id
    GROUP BY detail.mouvement_stock_ligne_id, denree.unite_inventaire_id
    HAVING COUNT(*) = COUNT(*) FILTER (
        WHERE conditionnement.conditionnement_id = denree.unite_inventaire_id
    )
) historique
WHERE ligne.id = historique.mouvement_stock_ligne_id;

-- Les autres mouvements conservent le résultat de la conversion actuellement
-- visible. Les nouveaux mouvements seront toujours enregistrés avec leur valeur figée.
WITH facteurs AS (
    SELECT reference.denree_id,
           niveau.conditionnement_id,
           EXP(SUM(LN(contenu.quantite_contenu::double precision))) AS facteur
    FROM campement.denree_fournisseur_conditionnement niveau
    JOIN campement.denree_fournisseur reference
      ON reference.id = niveau.reference_fournisseur_id AND reference.actif = TRUE
    JOIN campement.denree_fournisseur_conditionnement contenu
      ON contenu.reference_fournisseur_id = niveau.reference_fournisseur_id
     AND contenu.ordre >= niveau.ordre
    GROUP BY reference.id, reference.denree_id, niveau.id, niveau.conditionnement_id
), facteurs_inventaire AS (
    SELECT denree.id AS denree_id, COALESCE(MIN(facteurs.facteur), 1) AS facteur
    FROM campement.denree denree
    LEFT JOIN facteurs
      ON facteurs.denree_id = denree.id
     AND facteurs.conditionnement_id = denree.unite_inventaire_id
    GROUP BY denree.id
)
UPDATE campement.mouvement_stock_ligne ligne
SET quantite_unite_inventaire = ligne.quantite_unite_reference / facteurs_inventaire.facteur
FROM facteurs_inventaire
WHERE ligne.denree_id = facteurs_inventaire.denree_id
  AND ligne.quantite_unite_inventaire IS NULL;

ALTER TABLE campement.mouvement_stock_ligne
    ALTER COLUMN quantite_unite_inventaire SET NOT NULL;
