--liquibase formatted sql

--changeset campement:V014-contraindre-quantite-inventaire-positive splitStatements:true endDelimiter:;
--comment: Garantit une quantité d'inventaire strictement positive et corrige les valeurs historiques sous la précision NUMERIC(12,3)

UPDATE campement.mouvement_stock_ligne
SET quantite_unite_inventaire = 0.001
WHERE quantite_unite_inventaire <= 0;

ALTER TABLE campement.mouvement_stock_ligne
    ADD CONSTRAINT chk_mouvement_stock_ligne_quantite_inventaire
        CHECK (quantite_unite_inventaire > 0);
