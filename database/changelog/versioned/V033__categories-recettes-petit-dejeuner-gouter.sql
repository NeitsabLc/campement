--liquibase formatted sql

--changeset campement:V033-categories-recettes-petit-dejeuner-gouter
--comment: Ajoute les catégories de recettes petit-déjeuner et goûter

ALTER TABLE campement.recette
    DROP CONSTRAINT chk_recette_categorie,
    ADD CONSTRAINT chk_recette_categorie
        CHECK (categorie IN ('PETIT_DEJEUNER', 'ENTREE', 'PLAT', 'FROMAGE', 'DESSERT', 'GOUTER'));

--rollback ALTER TABLE campement.recette DROP CONSTRAINT chk_recette_categorie, ADD CONSTRAINT chk_recette_categorie CHECK (categorie IN ('ENTREE', 'PLAT', 'FROMAGE', 'DESSERT'));
