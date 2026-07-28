--liquibase formatted sql

--changeset campement:V008-refonte-menus-stocks-recettes splitStatements:true endDelimiter:;
--comment: Référentiel des conditionnements, recettes, menus multi-lignes et repas généraux

ALTER TABLE campement.unite
    ADD COLUMN utilisable_conditionnement BOOLEAN NOT NULL DEFAULT TRUE;

INSERT INTO campement.unite (nom, symbole, facteur_conversion, utilisable_conditionnement)
VALUES
    ('palette', 'palette', 1, TRUE),
    ('étage', 'étage', 1, TRUE),
    ('carton', 'carton', 1, TRUE),
    ('conserve', 'conserve', 1, TRUE),
    ('boîte', 'boîte', 1, TRUE),
    ('sachet', 'sachet', 1, TRUE),
    ('bouteille', 'bouteille', 1, TRUE),
    ('brique', 'brique', 1, TRUE),
    ('pot', 'pot', 1, TRUE),
    ('barquette', 'barquette', 1, TRUE),
    ('paquet', 'paquet', 1, TRUE)
ON CONFLICT DO NOTHING;

ALTER TABLE campement.denree
    ADD COLUMN unite_inventaire_id UUID;

ALTER TABLE campement.denree_fournisseur
    ALTER COLUMN reference DROP NOT NULL;

UPDATE campement.denree_fournisseur
SET reference = NULL
WHERE trim(reference) = '';

UPDATE campement.denree SET unite_inventaire_id = unite_reference_id;

ALTER TABLE campement.denree
    ALTER COLUMN unite_inventaire_id SET NOT NULL,
    ADD CONSTRAINT fk_denree_unite_inventaire FOREIGN KEY (unite_inventaire_id)
        REFERENCES campement.unite (id) ON DELETE RESTRICT;

CREATE INDEX idx_denree_unite_inventaire ON campement.denree (unite_inventaire_id);

ALTER TABLE campement.denree_fournisseur_conditionnement
    ADD COLUMN conditionnement_id UUID;

UPDATE campement.denree_fournisseur_conditionnement c
SET conditionnement_id = u.id
FROM campement.unite u
WHERE lower(u.nom) = lower(c.libelle) OR lower(u.symbole) = lower(c.libelle);

INSERT INTO campement.unite (nom, symbole, facteur_conversion, utilisable_conditionnement)
SELECT DISTINCT lower(trim(c.libelle)), concat('c', substr(md5(lower(trim(c.libelle))), 1, 8)), 1, TRUE
FROM campement.denree_fournisseur_conditionnement c
WHERE c.conditionnement_id IS NULL
ON CONFLICT DO NOTHING;

UPDATE campement.denree_fournisseur_conditionnement c
SET conditionnement_id = u.id
FROM campement.unite u
WHERE c.conditionnement_id IS NULL AND lower(u.nom) = lower(trim(c.libelle));

ALTER TABLE campement.denree_fournisseur_conditionnement
    ALTER COLUMN conditionnement_id SET NOT NULL,
    ADD CONSTRAINT fk_denree_fournisseur_conditionnement_type FOREIGN KEY (conditionnement_id)
        REFERENCES campement.unite (id) ON DELETE RESTRICT;

CREATE INDEX idx_denree_fournisseur_conditionnement_type
    ON campement.denree_fournisseur_conditionnement (conditionnement_id);

ALTER TABLE campement.mouvement_stock_ligne
    ADD COLUMN conditionnement_sortie_id UUID,
    ADD CONSTRAINT fk_mouvement_stock_ligne_conditionnement_sortie
        FOREIGN KEY (conditionnement_sortie_id) REFERENCES campement.unite(id) ON DELETE RESTRICT;

UPDATE campement.mouvement_stock_ligne ligne
SET conditionnement_sortie_id = denree.unite_reference_id
FROM campement.denree denree,
     campement.mouvement_stock mouvement,
     campement.type_mouvement type_mouvement
WHERE ligne.denree_id = denree.id
  AND ligne.mouvement_stock_id = mouvement.id
  AND mouvement.type_mouvement_id = type_mouvement.id
  AND type_mouvement.code = 'SORTIE';

CREATE INDEX idx_mouvement_stock_ligne_conditionnement_sortie
    ON campement.mouvement_stock_ligne(conditionnement_sortie_id);

-- Les chaînes historiques sont déjà ordonnées du plus grand au plus petit.
-- On matérialise leur unité physique terminale comme dernier niveau afin que
-- chaque niveau, y compris le plus petit, soit sélectionnable pour l'inventaire.
INSERT INTO campement.denree_fournisseur_conditionnement (
    reference_fournisseur_id, ordre, libelle, quantite_contenu,
    libelle_contenu, unite_contenu_id, conditionnement_id
)
SELECT dernier.reference_fournisseur_id,
       dernier.ordre + 1,
       unite.nom,
       1,
       NULL,
       unite.id,
       unite.id
FROM campement.denree_fournisseur_conditionnement dernier
JOIN campement.unite unite ON unite.id = dernier.unite_contenu_id
WHERE dernier.ordre = (
    SELECT max(c2.ordre)
    FROM campement.denree_fournisseur_conditionnement c2
    WHERE c2.reference_fournisseur_id = dernier.reference_fournisseur_id
)
AND dernier.conditionnement_id <> unite.id;

UPDATE campement.denree_fournisseur_conditionnement c
SET quantite_contenu = 1,
    libelle_contenu = NULL,
    unite_contenu_id = c.conditionnement_id
WHERE c.ordre = (
    SELECT max(c2.ordre)
    FROM campement.denree_fournisseur_conditionnement c2
    WHERE c2.reference_fournisseur_id = c.reference_fournisseur_id
)
AND c.conditionnement_id = c.unite_contenu_id;

ALTER TABLE campement.menu_denree DROP CONSTRAINT uq_menu_denree;
ALTER TABLE campement.menu_denree
    ADD COLUMN conditionnement_id UUID,
    ADD COLUMN categorie VARCHAR(20),
    ADD CONSTRAINT fk_menu_denree_conditionnement FOREIGN KEY (conditionnement_id)
        REFERENCES campement.unite (id) ON DELETE RESTRICT,
    ADD CONSTRAINT chk_menu_denree_categorie CHECK (categorie IS NULL OR categorie IN ('ENTREE', 'PLAT', 'FROMAGE', 'DESSERT'));

UPDATE campement.menu_denree md
SET conditionnement_id = d.unite_reference_id
FROM campement.denree d WHERE d.id = md.denree_id;
ALTER TABLE campement.menu_denree ALTER COLUMN conditionnement_id SET NOT NULL;
CREATE INDEX idx_menu_denree_conditionnement ON campement.menu_denree (conditionnement_id);

CREATE TABLE campement.recette (
    id UUID NOT NULL DEFAULT uuidv7(),
    sejour_id UUID NOT NULL,
    nom VARCHAR(150) NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_recette PRIMARY KEY (id),
    CONSTRAINT uq_recette_nom UNIQUE (sejour_id, nom),
    CONSTRAINT fk_recette_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE
);
CREATE INDEX idx_recette_sejour ON campement.recette(sejour_id);

CREATE TABLE campement.recette_denree (
    id UUID NOT NULL DEFAULT uuidv7(),
    recette_id UUID NOT NULL,
    denree_id UUID NOT NULL,
    conditionnement_id UUID NOT NULL,
    ordre SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_recette_denree PRIMARY KEY (id),
    CONSTRAINT fk_recette_denree_recette FOREIGN KEY (recette_id) REFERENCES campement.recette(id) ON DELETE CASCADE,
    CONSTRAINT fk_recette_denree_denree FOREIGN KEY (denree_id) REFERENCES campement.denree(id) ON DELETE RESTRICT,
    CONSTRAINT fk_recette_denree_conditionnement FOREIGN KEY (conditionnement_id) REFERENCES campement.unite(id) ON DELETE RESTRICT
);
CREATE INDEX idx_recette_denree_recette ON campement.recette_denree(recette_id);

CREATE TABLE campement.recette_denree_quantite (
    id UUID NOT NULL DEFAULT uuidv7(),
    recette_denree_id UUID NOT NULL,
    sejour_public_cible_id UUID NOT NULL,
    quantite_individuelle NUMERIC(12,3) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_recette_denree_quantite PRIMARY KEY (id),
    CONSTRAINT uq_recette_denree_quantite UNIQUE (recette_denree_id, sejour_public_cible_id),
    CONSTRAINT fk_recette_denree_quantite_ligne FOREIGN KEY (recette_denree_id) REFERENCES campement.recette_denree(id) ON DELETE CASCADE,
    CONSTRAINT fk_recette_denree_quantite_public FOREIGN KEY (sejour_public_cible_id) REFERENCES campement.sejour_public_cible(id) ON DELETE RESTRICT,
    CONSTRAINT chk_recette_denree_quantite CHECK (quantite_individuelle >= 0)
);

ALTER TABLE campement.menu DROP CONSTRAINT uq_menu_sejour_date_type;
ALTER TABLE campement.menu
    ALTER COLUMN date_menu DROP NOT NULL,
    ALTER COLUMN sejour_type_repas_id DROP NOT NULL,
    ADD COLUMN special_code VARCHAR(20),
    ADD CONSTRAINT chk_menu_identite CHECK (
        (special_code IS NULL AND date_menu IS NOT NULL AND sejour_type_repas_id IS NOT NULL)
        OR (special_code IN ('EXPLO', 'PIQUE_NIQUE_1', 'PIQUE_NIQUE_2') AND date_menu IS NULL AND sejour_type_repas_id IS NULL)
    );
CREATE UNIQUE INDEX uq_menu_sejour_date_type ON campement.menu(sejour_id, date_menu, sejour_type_repas_id) WHERE special_code IS NULL;
CREATE UNIQUE INDEX uq_menu_sejour_special ON campement.menu(sejour_id, special_code) WHERE special_code IS NOT NULL;

ALTER TABLE campement.sejour
    ADD COLUMN distribuer_gouter_dejeuner BOOLEAN NOT NULL DEFAULT FALSE;

INSERT INTO campement.sejour_type_repas (sejour_id, type_repas_id, ordre, actif, distribution_active)
SELECT s.id, tr.id, tr.ordre, TRUE, TRUE
FROM campement.sejour s CROSS JOIN campement.type_repas tr
WHERE tr.actif = TRUE
ON CONFLICT (sejour_id, type_repas_id) DO UPDATE SET actif = TRUE, distribution_active = TRUE;

--rollback non fourni : cette évolution transforme et enrichit des données de production.

--changeset campement:V008-compatibilite-imports-conditionnements splitStatements:false
--comment: Compatibilité des nouvelles références avec les imports historiques et intégrations externes

-- Compatibilité avec les imports historiques qui ne renseignent pas encore
-- explicitement les nouvelles colonnes.
CREATE OR REPLACE FUNCTION campement.completer_unite_inventaire_denree()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.unite_inventaire_id IS NULL THEN
        NEW.unite_inventaire_id := NEW.unite_reference_id;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_denree_unite_inventaire
BEFORE INSERT ON campement.denree
FOR EACH ROW EXECUTE FUNCTION campement.completer_unite_inventaire_denree();

CREATE OR REPLACE FUNCTION campement.completer_type_conditionnement()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    unite_id UUID;
BEGIN
    IF NEW.conditionnement_id IS NULL THEN
        SELECT id INTO unite_id
        FROM campement.unite
        WHERE lower(nom) = lower(trim(NEW.libelle))
           OR lower(symbole) = lower(trim(NEW.libelle))
        LIMIT 1;

        IF unite_id IS NULL THEN
            INSERT INTO campement.unite (nom, symbole, facteur_conversion, utilisable_conditionnement)
            VALUES (
                lower(trim(NEW.libelle)),
                concat('c', substr(md5(lower(trim(NEW.libelle))), 1, 8)),
                1,
                TRUE
            )
            ON CONFLICT (nom) DO UPDATE SET utilisable_conditionnement = TRUE
            RETURNING id INTO unite_id;
        END IF;

        NEW.conditionnement_id := unite_id;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_conditionnement_type
BEFORE INSERT ON campement.denree_fournisseur_conditionnement
FOR EACH ROW EXECUTE FUNCTION campement.completer_type_conditionnement();

--rollback DROP TRIGGER trg_conditionnement_type ON campement.denree_fournisseur_conditionnement;
--rollback DROP FUNCTION campement.completer_type_conditionnement();
--rollback DROP TRIGGER trg_denree_unite_inventaire ON campement.denree;
--rollback DROP FUNCTION campement.completer_unite_inventaire_denree();
