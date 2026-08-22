--liquibase formatted sql

--changeset campement:V035-conserver-quantites-saisies-mouvements splitStatements:true endDelimiter:;
--comment: Conserve les mouvements dans leur conditionnement saisi et supprime les quantités converties figées

ALTER TABLE campement.mouvement_stock_ligne
    ADD COLUMN quantite_saisie NUMERIC(12,3);

-- Les anciennes entrées conditionnées doivent toutes disposer de leur détail
-- natif. Pour les très anciennes lignes qui n'en ont pas, la quantité de
-- référence est rattachée au niveau terminal de leur référence fournisseur.
INSERT INTO campement.mouvement_stock_ligne_conditionnement (
    mouvement_stock_ligne_id,
    conditionnement_id,
    quantite
)
SELECT ligne.id,
       terminal.id,
       ligne.quantite_unite_reference
FROM campement.mouvement_stock_ligne ligne
JOIN LATERAL (
    SELECT niveau.id
    FROM campement.denree_fournisseur_conditionnement niveau
    WHERE niveau.reference_fournisseur_id = ligne.reference_fournisseur_id
    ORDER BY niveau.ordre DESC
    LIMIT 1
) terminal ON TRUE
WHERE ligne.reference_fournisseur_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM campement.mouvement_stock_ligne_conditionnement detail
      WHERE detail.mouvement_stock_ligne_id = ligne.id
  );

-- Les lignes simples les plus anciennes ne renseignaient pas toujours leur
-- conditionnement. Leur unité de référence était alors l'unité effectivement
-- saisie par l'utilisateur.
UPDATE campement.mouvement_stock_ligne ligne
SET conditionnement_sortie_id = denree.unite_reference_id
FROM campement.denree denree
WHERE ligne.denree_id = denree.id
  AND ligne.reference_fournisseur_id IS NULL
  AND ligne.conditionnement_sortie_id IS NULL;

UPDATE campement.mouvement_stock_ligne
SET conditionnement_sortie_id = NULL,
    quantite_saisie = NULL
WHERE reference_fournisseur_id IS NOT NULL;

-- Reconstitue la quantité saisie à partir de la quantité terminale historique
-- et du conditionnement conservé. Les références actives sont prioritaires ;
-- les références archivées servent de repli pour préserver l'historique.
WITH facteurs_par_reference AS (
    SELECT reference.id AS reference_id,
           reference.denree_id,
           reference.actif,
           niveau.conditionnement_id,
           EXP(SUM(LN(contenu.quantite_contenu::DOUBLE PRECISION))) AS facteur
    FROM campement.denree_fournisseur_conditionnement niveau
    JOIN campement.denree_fournisseur reference
      ON reference.id = niveau.reference_fournisseur_id
    JOIN campement.denree_fournisseur_conditionnement contenu
      ON contenu.reference_fournisseur_id = niveau.reference_fournisseur_id
     AND contenu.ordre >= niveau.ordre
    GROUP BY reference.id, reference.denree_id, reference.actif,
             niveau.id, niveau.conditionnement_id
), facteurs AS (
    SELECT denree_id,
           conditionnement_id,
           COALESCE(
               MIN(facteur) FILTER (WHERE actif = TRUE),
               MIN(facteur)
           ) AS facteur
    FROM facteurs_par_reference
    GROUP BY denree_id, conditionnement_id
), conversions AS (
    SELECT ligne.id,
           ligne.quantite_unite_reference,
           ligne.quantite_unite_inventaire,
           ligne.conditionnement_sortie_id,
           denree.unite_reference_id,
           COALESCE(facteurs.facteur, 1) AS facteur
    FROM campement.mouvement_stock_ligne ligne
    JOIN campement.denree denree ON denree.id = ligne.denree_id
    LEFT JOIN facteurs
      ON facteurs.denree_id = denree.id
     AND facteurs.conditionnement_id = ligne.conditionnement_sortie_id
    WHERE ligne.reference_fournisseur_id IS NULL
)
UPDATE campement.mouvement_stock_ligne ligne
SET quantite_saisie = GREATEST(
    0.001,
    ROUND((
        CASE
            -- L'ancien calcul traitait parfois à tort l'unité de référence
            -- comme terminale. Dans ce cas les deux quantités figées sont
            -- identiques et la valeur stockée est déjà la quantité saisie.
            WHEN conversions.conditionnement_sortie_id = conversions.unite_reference_id
             AND conversions.quantite_unite_reference = conversions.quantite_unite_inventaire
                THEN conversions.quantite_unite_reference
            ELSE conversions.quantite_unite_reference / conversions.facteur
        END
    )::NUMERIC, 3)
)
FROM conversions
WHERE ligne.id = conversions.id;

ALTER TABLE campement.mouvement_stock_ligne
    DROP CONSTRAINT chk_mouvement_stock_ligne_quantite,
    DROP CONSTRAINT chk_mouvement_stock_ligne_quantite_inventaire,
    DROP COLUMN quantite_unite_reference,
    DROP COLUMN quantite_unite_inventaire;

ALTER TABLE campement.mouvement_stock_ligne
    RENAME COLUMN conditionnement_sortie_id TO conditionnement_saisie_id;

ALTER TABLE campement.mouvement_stock_ligne
    RENAME CONSTRAINT fk_mouvement_stock_ligne_conditionnement_sortie
        TO fk_mouvement_stock_ligne_conditionnement_saisie;

ALTER INDEX campement.idx_mouvement_stock_ligne_conditionnement_sortie
    RENAME TO idx_mouvement_stock_ligne_conditionnement_saisie;

ALTER TABLE campement.mouvement_stock_ligne
    ADD CONSTRAINT chk_mouvement_stock_ligne_quantite_saisie
        CHECK (quantite_saisie IS NULL OR quantite_saisie > 0),
    ADD CONSTRAINT chk_mouvement_stock_ligne_stockage_natif
        CHECK (
            (reference_fournisseur_id IS NULL
             AND conditionnement_saisie_id IS NOT NULL
             AND quantite_saisie IS NOT NULL)
            OR
            (reference_fournisseur_id IS NOT NULL
             AND conditionnement_saisie_id IS NULL
             AND quantite_saisie IS NULL)
        );

COMMENT ON COLUMN campement.mouvement_stock_ligne.quantite_saisie IS
    'Quantité brute saisie par l’utilisateur dans conditionnement_saisie_id ; NULL pour une ligne détaillée par niveaux fournisseur.';
