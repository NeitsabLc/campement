--liquibase formatted sql

--changeset campement:V013-recalculer-entrees-par-niveaux-conditionnement splitStatements:true endDelimiter:;
--comment: Recalcule les entrées historiques entre niveaux de conditionnement sans dépendre de l'unité terminale

WITH facteurs AS (
    SELECT niveau.reference_fournisseur_id,
           niveau.id AS conditionnement_id,
           niveau.conditionnement_id AS unite_id,
           EXP(SUM(LN(contenu.quantite_contenu::double precision))) AS facteur
    FROM campement.denree_fournisseur_conditionnement niveau
    JOIN campement.denree_fournisseur_conditionnement contenu
      ON contenu.reference_fournisseur_id = niveau.reference_fournisseur_id
     AND contenu.ordre >= niveau.ordre
    GROUP BY niveau.reference_fournisseur_id, niveau.id, niveau.conditionnement_id
), facteurs_inventaire AS (
    SELECT ligne.id AS ligne_id, facteur.facteur
    FROM campement.mouvement_stock_ligne ligne
    JOIN campement.denree denree ON denree.id = ligne.denree_id
    JOIN facteurs facteur
      ON facteur.reference_fournisseur_id = ligne.reference_fournisseur_id
     AND facteur.unite_id = denree.unite_inventaire_id
), quantites_historiques AS (
    SELECT detail.mouvement_stock_ligne_id AS ligne_id,
           SUM(detail.quantite * facteur.facteur / inventaire.facteur) AS quantite
    FROM campement.mouvement_stock_ligne_conditionnement detail
    JOIN facteurs facteur ON facteur.conditionnement_id = detail.conditionnement_id
    JOIN facteurs_inventaire inventaire
      ON inventaire.ligne_id = detail.mouvement_stock_ligne_id
    GROUP BY detail.mouvement_stock_ligne_id
)
UPDATE campement.mouvement_stock_ligne ligne
SET quantite_unite_inventaire = historique.quantite
FROM quantites_historiques historique
WHERE ligne.id = historique.ligne_id;
