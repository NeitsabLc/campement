--liquibase formatted sql

--changeset campement:V034-regimes-alimentaires
--comment: Ajoute les régimes alimentaires aux unités, recettes et menus

ALTER TABLE campement.groupe
    ADD COLUMN nombre_vegetariens INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN nombre_sans_lactose INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN nombre_sans_gluten INTEGER NOT NULL DEFAULT 0,
    ADD CONSTRAINT chk_groupe_nombre_vegetariens CHECK (nombre_vegetariens >= 0),
    ADD CONSTRAINT chk_groupe_nombre_sans_lactose CHECK (nombre_sans_lactose >= 0),
    ADD CONSTRAINT chk_groupe_nombre_sans_gluten CHECK (nombre_sans_gluten >= 0);

ALTER TABLE campement.recette_denree
    ADD COLUMN regime VARCHAR(20),
    ADD CONSTRAINT chk_recette_denree_regime
        CHECK (regime IS NULL OR regime IN ('VEGETARIEN', 'SANS_LACTOSE', 'SANS_GLUTEN'));

ALTER TABLE campement.menu_denree
    ADD COLUMN regime VARCHAR(20),
    ADD CONSTRAINT chk_menu_denree_regime
        CHECK (regime IS NULL OR regime IN ('VEGETARIEN', 'SANS_LACTOSE', 'SANS_GLUTEN'));

--rollback ALTER TABLE campement.menu_denree DROP CONSTRAINT chk_menu_denree_regime, DROP COLUMN regime;
--rollback ALTER TABLE campement.recette_denree DROP CONSTRAINT chk_recette_denree_regime, DROP COLUMN regime;
--rollback ALTER TABLE campement.groupe DROP CONSTRAINT chk_groupe_nombre_sans_gluten, DROP CONSTRAINT chk_groupe_nombre_sans_lactose, DROP CONSTRAINT chk_groupe_nombre_vegetariens, DROP COLUMN nombre_sans_gluten, DROP COLUMN nombre_sans_lactose, DROP COLUMN nombre_vegetariens;
