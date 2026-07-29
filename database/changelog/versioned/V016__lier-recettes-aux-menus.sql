--liquibase formatted sql

--changeset campement:V016-lier-recettes-aux-menus
--comment: Conserver la provenance des lignes de menu ajoutées depuis une recette

ALTER TABLE campement.menu_denree
    ADD COLUMN recette_id UUID,
    ADD COLUMN recette_instance_id UUID,
    ADD CONSTRAINT fk_menu_denree_recette
        FOREIGN KEY (recette_id) REFERENCES campement.recette(id) ON DELETE RESTRICT,
    ADD CONSTRAINT chk_menu_denree_recette_instance
        CHECK ((recette_id IS NULL) = (recette_instance_id IS NULL));

CREATE INDEX idx_menu_denree_recette ON campement.menu_denree(recette_id);
CREATE INDEX idx_menu_denree_recette_instance ON campement.menu_denree(recette_instance_id);

--rollback ALTER TABLE campement.menu_denree DROP CONSTRAINT chk_menu_denree_recette_instance, DROP CONSTRAINT fk_menu_denree_recette, DROP COLUMN recette_instance_id, DROP COLUMN recette_id;
