ALTER TABLE campement.recette
    ADD COLUMN categorie VARCHAR(20) NOT NULL DEFAULT 'PLAT',
    ADD CONSTRAINT chk_recette_categorie
        CHECK (categorie IN ('ENTREE', 'PLAT', 'FROMAGE', 'DESSERT'));
