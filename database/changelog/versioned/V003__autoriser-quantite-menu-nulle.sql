--liquibase formatted sql

--changeset blecaer:V003 splitStatements:true endDelimiter:;
--comment: Autoriser une quantité individuelle nulle dans la composition d'un menu

ALTER TABLE campement.menu_denree_quantite
    DROP CONSTRAINT chk_menu_denree_quantite_quantite;

ALTER TABLE campement.menu_denree_quantite
    ADD CONSTRAINT chk_menu_denree_quantite_quantite
        CHECK (quantite_individuelle >= 0);

--rollback ALTER TABLE campement.menu_denree_quantite DROP CONSTRAINT chk_menu_denree_quantite_quantite;
--rollback ALTER TABLE campement.menu_denree_quantite ADD CONSTRAINT chk_menu_denree_quantite_quantite CHECK (quantite_individuelle > 0);
