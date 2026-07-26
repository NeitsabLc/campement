--liquibase formatted sql

--changeset blecaer:V002 splitStatements:true endDelimiter:;
--comment: Initialisation du schéma Campement

CREATE TABLE campement.type_repas
(
    id          UUID        NOT NULL DEFAULT uuidv7(),
    code        VARCHAR(50) NOT NULL,
    libelle     VARCHAR(150) NOT NULL,
    ordre       SMALLINT    NOT NULL DEFAULT 0,
    actif       BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_type_repas PRIMARY KEY (id),
    CONSTRAINT uq_type_repas_code UNIQUE (code),
    CONSTRAINT chk_type_repas_ordre CHECK (ordre >= 0)
);

CREATE TABLE campement.type_mouvement
(
    id          UUID         NOT NULL DEFAULT uuidv7(),
    code        VARCHAR(50)  NOT NULL,
    libelle     VARCHAR(150) NOT NULL,
    ordre       SMALLINT     NOT NULL DEFAULT 0,
    actif       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_type_mouvement PRIMARY KEY (id),
    CONSTRAINT uq_type_mouvement_code UNIQUE (code),
    CONSTRAINT chk_type_mouvement_ordre CHECK (ordre >= 0)
);

CREATE TABLE campement.origine_mouvement
(
    id          UUID         NOT NULL DEFAULT uuidv7(),
    sejour_id   UUID         NOT NULL,
    code        VARCHAR(50)  NOT NULL,
    libelle     VARCHAR(150) NOT NULL,
    ordre       SMALLINT     NOT NULL DEFAULT 0,
    actif       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_origine_mouvement PRIMARY KEY (id),
    CONSTRAINT uq_origine_mouvement_code UNIQUE (sejour_id, code),
    CONSTRAINT chk_origine_mouvement_ordre CHECK (ordre >= 0)
);

CREATE TABLE campement.public_cible
(
    id          UUID         NOT NULL DEFAULT uuidv7(),
    code        VARCHAR(50)  NOT NULL,
    libelle     VARCHAR(150) NOT NULL,
    ordre       SMALLINT     NOT NULL DEFAULT 0,
    actif       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_public_cible PRIMARY KEY (id),
    CONSTRAINT uq_public_cible_code UNIQUE (code),
    CONSTRAINT chk_public_cible_ordre CHECK (ordre >= 0)
);

CREATE TABLE campement.sejour
(
    id          UUID         NOT NULL DEFAULT uuidv7(),
    nom         VARCHAR(150) NOT NULL,
    date_debut  DATE         NOT NULL,
    date_fin    DATE         NOT NULL,
    lieu        VARCHAR(150),
    actif       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_sejour PRIMARY KEY (id),
    CONSTRAINT chk_sejour_dates CHECK (date_fin >= date_debut)
);

CREATE INDEX idx_sejour_date
    ON campement.sejour (date_debut);

ALTER TABLE campement.origine_mouvement
    ADD CONSTRAINT fk_origine_mouvement_sejour
        FOREIGN KEY (sejour_id) REFERENCES campement.sejour (id) ON DELETE CASCADE;

CREATE INDEX idx_origine_mouvement_sejour
    ON campement.origine_mouvement (sejour_id);

CREATE TABLE campement.sejour_type_repas
(
    id                  UUID        NOT NULL DEFAULT uuidv7(),
    sejour_id           UUID        NOT NULL,
    type_repas_id       UUID        NOT NULL,
    distribution_active BOOLEAN     NOT NULL DEFAULT TRUE,
    ordre               SMALLINT    NOT NULL DEFAULT 0,
    actif               BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_sejour_type_repas PRIMARY KEY (id),

    CONSTRAINT uq_sejour_type_repas
        UNIQUE (sejour_id, type_repas_id),

    CONSTRAINT fk_sejour_type_repas_sejour
        FOREIGN KEY (sejour_id)
            REFERENCES campement.sejour (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_sejour_type_repas_type_repas
        FOREIGN KEY (type_repas_id)
            REFERENCES campement.type_repas (id)
            ON DELETE RESTRICT,

    CONSTRAINT chk_sejour_type_repas_ordre
        CHECK (ordre >= 0)
);

CREATE INDEX idx_sejour_type_repas_sejour
    ON campement.sejour_type_repas (sejour_id);

CREATE INDEX idx_sejour_type_repas_type_repas
    ON campement.sejour_type_repas (type_repas_id);

CREATE TABLE campement.sejour_public_cible
(
    id                UUID        NOT NULL DEFAULT uuidv7(),
    sejour_id         UUID        NOT NULL,
    public_cible_id   UUID        NOT NULL,
    ordre             SMALLINT    NOT NULL DEFAULT 0,
    actif             BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_sejour_public_cible PRIMARY KEY (id),
    CONSTRAINT uq_sejour_public_cible UNIQUE (sejour_id, public_cible_id),
    CONSTRAINT fk_sejour_public_cible_sejour
        FOREIGN KEY (sejour_id) REFERENCES campement.sejour (id) ON DELETE CASCADE,
    CONSTRAINT fk_sejour_public_cible_public_cible
        FOREIGN KEY (public_cible_id) REFERENCES campement.public_cible (id) ON DELETE RESTRICT,
    CONSTRAINT chk_sejour_public_cible_ordre CHECK (ordre >= 0)
);

CREATE INDEX idx_sejour_public_cible_sejour
    ON campement.sejour_public_cible (sejour_id);

CREATE INDEX idx_sejour_public_cible_public_cible
    ON campement.sejour_public_cible (public_cible_id);

CREATE TABLE campement.unite
(
    id                  UUID          NOT NULL DEFAULT uuidv7(),
    nom                 VARCHAR(50)   NOT NULL,
    symbole             VARCHAR(10)   NOT NULL,
    facteur_conversion  NUMERIC(12,6) NOT NULL,
    actif               BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMPTZ   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_unite PRIMARY KEY (id),
    CONSTRAINT uq_unite_nom UNIQUE (nom),
    CONSTRAINT uq_unite_symbole UNIQUE (symbole),
    CONSTRAINT chk_unite_conversion CHECK (facteur_conversion > 0)
);

CREATE TABLE campement.denree
(
    id                      UUID           NOT NULL DEFAULT uuidv7(),
    sejour_id               UUID           NOT NULL,
    nom                     VARCHAR(150)   NOT NULL,
    unite_reference_id      UUID           NOT NULL,
    stock_min               NUMERIC(12,3),
    actif                   BOOLEAN        NOT NULL DEFAULT TRUE,
    created_at              TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_denree PRIMARY KEY (id),
    CONSTRAINT uq_denree_nom UNIQUE (sejour_id, nom),

    CONSTRAINT fk_denree_sejour
        FOREIGN KEY (sejour_id) REFERENCES campement.sejour (id) ON DELETE CASCADE,

    CONSTRAINT fk_denree_unite
        FOREIGN KEY (unite_reference_id)
            REFERENCES campement.unite (id)
            ON DELETE RESTRICT,

    CONSTRAINT chk_denree_stock_min
        CHECK (stock_min IS NULL OR stock_min >= 0)
);

CREATE INDEX idx_denree_unite
    ON campement.denree (unite_reference_id);

CREATE INDEX idx_denree_sejour
    ON campement.denree (sejour_id);

CREATE TABLE campement.fournisseur
(
    id          UUID         NOT NULL DEFAULT uuidv7(),
    sejour_id   UUID         NOT NULL,
    nom         VARCHAR(150) NOT NULL,
    telephone   VARCHAR(30),
    email       VARCHAR(150),
    adresse     TEXT,
    actif       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_fournisseur PRIMARY KEY (id),
    CONSTRAINT uq_fournisseur_nom UNIQUE (sejour_id, nom),
    CONSTRAINT fk_fournisseur_sejour
        FOREIGN KEY (sejour_id) REFERENCES campement.sejour (id) ON DELETE CASCADE
);

CREATE INDEX idx_fournisseur_nom
    ON campement.fournisseur (nom);

CREATE INDEX idx_fournisseur_sejour
    ON campement.fournisseur (sejour_id);

CREATE TABLE campement.denree_fournisseur
(
    id              UUID         NOT NULL DEFAULT uuidv7(),
    fournisseur_id  UUID         NOT NULL,
    denree_id       UUID         NOT NULL,
    reference       VARCHAR(100) NOT NULL,
    designation     VARCHAR(200) NOT NULL,
    actif           BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_denree_fournisseur PRIMARY KEY (id),

    CONSTRAINT uq_denree_fournisseur
        UNIQUE (fournisseur_id, reference),

    CONSTRAINT fk_denree_fournisseur_fournisseur
        FOREIGN KEY (fournisseur_id)
            REFERENCES campement.fournisseur (id)
            ON DELETE RESTRICT,

    CONSTRAINT fk_denree_fournisseur_denree
        FOREIGN KEY (denree_id)
            REFERENCES campement.denree (id)
            ON DELETE RESTRICT
);

CREATE INDEX idx_denree_fournisseur_denree
    ON campement.denree_fournisseur (denree_id);

CREATE INDEX idx_denree_fournisseur_fournisseur
    ON campement.denree_fournisseur (fournisseur_id);

CREATE TABLE campement.denree_fournisseur_conditionnement
(
    id                       UUID           NOT NULL DEFAULT uuidv7(),
    reference_fournisseur_id UUID           NOT NULL,
    ordre                    SMALLINT       NOT NULL,
    libelle                  VARCHAR(50)    NOT NULL,
    quantite_contenu         NUMERIC(12,3) NOT NULL,
    libelle_contenu          VARCHAR(50),
    unite_contenu_id         UUID,
    created_at               TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_denree_fournisseur_conditionnement PRIMARY KEY (id),

    CONSTRAINT uq_denree_fournisseur_conditionnement
        UNIQUE (reference_fournisseur_id, ordre),

    CONSTRAINT chk_denree_fournisseur_conditionnement_ordre
        CHECK (ordre > 0),

    CONSTRAINT chk_denree_fournisseur_conditionnement_quantite
        CHECK (quantite_contenu > 0),

    CONSTRAINT chk_denree_fournisseur_conditionnement_contenu
        CHECK ((libelle_contenu IS NULL) <> (unite_contenu_id IS NULL)),

    CONSTRAINT fk_denree_fournisseur_conditionnement_reference
        FOREIGN KEY (reference_fournisseur_id)
            REFERENCES campement.denree_fournisseur (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_denree_fournisseur_conditionnement_unite
        FOREIGN KEY (unite_contenu_id)
            REFERENCES campement.unite (id)
            ON DELETE RESTRICT
);

CREATE INDEX idx_denree_fournisseur_conditionnement_reference
    ON campement.denree_fournisseur_conditionnement (reference_fournisseur_id);

CREATE INDEX idx_denree_fournisseur_conditionnement_unite
    ON campement.denree_fournisseur_conditionnement (unite_contenu_id);

CREATE TABLE campement.utilisateur
(
    id              UUID         NOT NULL DEFAULT uuidv7(),
    groupe_id       UUID,
    email           VARCHAR(180) NOT NULL,
    mot_de_passe    VARCHAR(255) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    nom             VARCHAR(100) NOT NULL,
    roles           JSONB        NOT NULL,
    actif           BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP(0),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP(0),

    CONSTRAINT pk_utilisateur PRIMARY KEY (id),
    CONSTRAINT uq_utilisateur_email UNIQUE (email),
    CONSTRAINT chk_utilisateur_roles
        CHECK (
            jsonb_typeof(roles) = 'array'
                AND jsonb_array_length(roles) = 1
            AND roles ->> 0 IN (
                'ROLE_GESTIONNAIRE',
                'ROLE_GROUPE',
                'ROLE_ADMIN',
                'ROLE_TECHNIQUE'
            )
        )
);

CREATE TABLE campement.groupe
(
    id          UUID         NOT NULL DEFAULT uuidv7(),
    sejour_id   UUID         NOT NULL,
    nom              VARCHAR(150) NOT NULL,
    effectif_jeune   INTEGER      NOT NULL DEFAULT 0,
    effectif_adulte  INTEGER      NOT NULL DEFAULT 0,
    type             VARCHAR(30)  NOT NULL,
    commentaire      TEXT,
    actif       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_groupe PRIMARY KEY (id),

    CONSTRAINT uq_groupe_sejour_nom
        UNIQUE (sejour_id, nom),

    CONSTRAINT fk_groupe_sejour
        FOREIGN KEY (sejour_id)
            REFERENCES campement.sejour (id)
            ON DELETE CASCADE,

    CONSTRAINT chk_groupe_effectif_jeune
        CHECK (effectif_jeune >= 0),

    CONSTRAINT chk_groupe_effectif_adulte
        CHECK (effectif_adulte >= 0),

    CONSTRAINT chk_groupe_type
        CHECK (type IN (
            'scouts-guides',
            'louveteaux-jeannettes',
            'pionniers-caravelles'
        ))
);

CREATE INDEX idx_groupe_sejour
    ON campement.groupe (sejour_id);

ALTER TABLE campement.utilisateur
    ADD CONSTRAINT fk_utilisateur_groupe
        FOREIGN KEY (groupe_id)
            REFERENCES campement.groupe (id)
            ON DELETE RESTRICT;

CREATE INDEX idx_utilisateur_groupe
    ON campement.utilisateur (groupe_id);

CREATE TABLE campement.utilisateur_sejour
(
    utilisateur_id UUID NOT NULL,
    sejour_id      UUID NOT NULL,

    CONSTRAINT pk_utilisateur_sejour
        PRIMARY KEY (utilisateur_id, sejour_id),

    CONSTRAINT fk_utilisateur_sejour_utilisateur
        FOREIGN KEY (utilisateur_id)
            REFERENCES campement.utilisateur (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_utilisateur_sejour_sejour
        FOREIGN KEY (sejour_id)
            REFERENCES campement.sejour (id)
            ON DELETE CASCADE
);

CREATE INDEX idx_utilisateur_sejour_sejour
    ON campement.utilisateur_sejour (sejour_id);

CREATE TABLE campement.menu
(
    id                    UUID         NOT NULL DEFAULT uuidv7(),
    sejour_id             UUID         NOT NULL,
    sejour_type_repas_id  UUID         NOT NULL,
    date_menu             DATE         NOT NULL,
    nom                   VARCHAR(150),
    commentaire           TEXT,
    actif                 BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at            TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_menu PRIMARY KEY (id),

    CONSTRAINT uq_menu_sejour_date_type
        UNIQUE (sejour_id, date_menu, sejour_type_repas_id),

    CONSTRAINT fk_menu_sejour
        FOREIGN KEY (sejour_id)
            REFERENCES campement.sejour (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_menu_sejour_type_repas
        FOREIGN KEY (sejour_type_repas_id)
            REFERENCES campement.sejour_type_repas (id)
            ON DELETE RESTRICT
);

CREATE INDEX idx_menu_sejour
    ON campement.menu (sejour_id);

CREATE INDEX idx_menu_date
    ON campement.menu (date_menu);

CREATE INDEX idx_menu_sejour_type_repas
    ON campement.menu (sejour_type_repas_id);

CREATE TABLE campement.menu_denree
(
    id          UUID        NOT NULL DEFAULT uuidv7(),
    menu_id     UUID        NOT NULL,
    denree_id   UUID        NOT NULL,
    ordre       SMALLINT    NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_menu_denree PRIMARY KEY (id),

    CONSTRAINT uq_menu_denree
        UNIQUE (menu_id, denree_id),

    CONSTRAINT fk_menu_denree_menu
        FOREIGN KEY (menu_id)
            REFERENCES campement.menu (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_menu_denree_denree
        FOREIGN KEY (denree_id)
            REFERENCES campement.denree (id)
            ON DELETE RESTRICT,

    CONSTRAINT chk_menu_denree_ordre
        CHECK (ordre >= 0)
);

CREATE INDEX idx_menu_denree_menu
    ON campement.menu_denree (menu_id);

CREATE INDEX idx_menu_denree_denree
    ON campement.menu_denree (denree_id);

CREATE TABLE campement.menu_denree_quantite
(
    id                         UUID           NOT NULL DEFAULT uuidv7(),
    menu_denree_id             UUID           NOT NULL,
    sejour_public_cible_id      UUID           NOT NULL,
    quantite_individuelle      NUMERIC(12,3) NOT NULL,
    created_at                 TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_menu_denree_quantite PRIMARY KEY (id),

    CONSTRAINT uq_menu_denree_quantite
        UNIQUE (menu_denree_id, sejour_public_cible_id),

    CONSTRAINT fk_menu_denree_quantite_menu_denree
        FOREIGN KEY (menu_denree_id)
            REFERENCES campement.menu_denree (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_menu_denree_quantite_sejour_public_cible
        FOREIGN KEY (sejour_public_cible_id)
            REFERENCES campement.sejour_public_cible (id)
            ON DELETE RESTRICT,

    CONSTRAINT chk_menu_denree_quantite_quantite
        CHECK (quantite_individuelle > 0)
);

CREATE INDEX idx_menu_denree_quantite_menu_denree
    ON campement.menu_denree_quantite (menu_denree_id);

CREATE INDEX idx_menu_denree_quantite_sejour_public_cible
    ON campement.menu_denree_quantite (sejour_public_cible_id);

CREATE TABLE campement.mouvement_stock
(
    id                      UUID         NOT NULL DEFAULT uuidv7(),
    sejour_id               UUID         NOT NULL,
    utilisateur_id          UUID         NOT NULL,
    groupe_id               UUID,
    menu_id                 UUID,
    type_mouvement_id       UUID         NOT NULL,
    origine_mouvement_id    UUID         NOT NULL,
    date_mouvement          TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reference_document      VARCHAR(100),
    commentaire             TEXT,
    created_at              TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_mouvement_stock PRIMARY KEY (id),

    CONSTRAINT fk_mouvement_stock_sejour
        FOREIGN KEY (sejour_id)
            REFERENCES campement.sejour (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_mouvement_stock_utilisateur
        FOREIGN KEY (utilisateur_id)
            REFERENCES campement.utilisateur (id)
            ON DELETE RESTRICT,

    CONSTRAINT fk_mouvement_stock_groupe
        FOREIGN KEY (groupe_id)
            REFERENCES campement.groupe (id)
            ON DELETE RESTRICT,

    CONSTRAINT fk_mouvement_stock_menu
        FOREIGN KEY (menu_id)
            REFERENCES campement.menu (id)
            ON DELETE RESTRICT,

    CONSTRAINT fk_mouvement_stock_type
        FOREIGN KEY (type_mouvement_id)
            REFERENCES campement.type_mouvement (id)
            ON DELETE RESTRICT,

    CONSTRAINT fk_mouvement_stock_origine
        FOREIGN KEY (origine_mouvement_id)
            REFERENCES campement.origine_mouvement (id)
            ON DELETE RESTRICT
);

CREATE INDEX idx_mouvement_stock_sejour
    ON campement.mouvement_stock (sejour_id);

CREATE INDEX idx_mouvement_stock_date
    ON campement.mouvement_stock (date_mouvement);

CREATE INDEX idx_mouvement_stock_groupe
    ON campement.mouvement_stock (groupe_id);

CREATE INDEX idx_mouvement_stock_menu
    ON campement.mouvement_stock (menu_id);

CREATE INDEX idx_mouvement_stock_utilisateur
    ON campement.mouvement_stock (utilisateur_id);

CREATE INDEX idx_mouvement_stock_type
    ON campement.mouvement_stock (type_mouvement_id);

CREATE INDEX idx_mouvement_stock_origine
    ON campement.mouvement_stock (origine_mouvement_id);

CREATE TABLE campement.mouvement_stock_ligne
(
    id                          UUID           NOT NULL DEFAULT uuidv7(),
    mouvement_stock_id         UUID           NOT NULL,
    denree_id                   UUID           NOT NULL,
    reference_fournisseur_id    UUID,
    quantite_unite_reference    NUMERIC(12,3) NOT NULL,
    created_at                  TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_mouvement_stock_ligne PRIMARY KEY (id),

    CONSTRAINT uq_mouvement_stock_ligne_denree
        UNIQUE (mouvement_stock_id, denree_id),

    CONSTRAINT chk_mouvement_stock_ligne_quantite
        CHECK (quantite_unite_reference > 0),

    CONSTRAINT fk_mouvement_stock_ligne_mouvement
        FOREIGN KEY (mouvement_stock_id)
            REFERENCES campement.mouvement_stock (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_mouvement_stock_ligne_denree
        FOREIGN KEY (denree_id)
            REFERENCES campement.denree (id)
            ON DELETE RESTRICT,

    CONSTRAINT fk_mouvement_stock_ligne_reference_fournisseur
        FOREIGN KEY (reference_fournisseur_id)
            REFERENCES campement.denree_fournisseur (id)
            ON DELETE RESTRICT
);

CREATE INDEX idx_mouvement_stock_ligne_mouvement
    ON campement.mouvement_stock_ligne (mouvement_stock_id);

CREATE INDEX idx_mouvement_stock_ligne_denree
    ON campement.mouvement_stock_ligne (denree_id);

CREATE INDEX idx_mouvement_stock_ligne_reference_fournisseur
    ON campement.mouvement_stock_ligne (reference_fournisseur_id);

CREATE TABLE campement.mouvement_stock_ligne_conditionnement
(
    id                          UUID           NOT NULL DEFAULT uuidv7(),
    mouvement_stock_ligne_id    UUID           NOT NULL,
    conditionnement_id          UUID           NOT NULL,
    quantite                    NUMERIC(12,3) NOT NULL,
    created_at                  TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_mouvement_stock_ligne_conditionnement PRIMARY KEY (id),

    CONSTRAINT uq_mouvement_stock_ligne_conditionnement
        UNIQUE (mouvement_stock_ligne_id, conditionnement_id),

    CONSTRAINT chk_mouvement_stock_ligne_conditionnement_quantite
        CHECK (quantite > 0),

    CONSTRAINT fk_mouvement_stock_ligne_conditionnement_ligne
        FOREIGN KEY (mouvement_stock_ligne_id)
            REFERENCES campement.mouvement_stock_ligne (id)
            ON DELETE CASCADE,

    CONSTRAINT fk_mouvement_stock_ligne_conditionnement_conditionnement
        FOREIGN KEY (conditionnement_id)
            REFERENCES campement.denree_fournisseur_conditionnement (id)
            ON DELETE RESTRICT
);

CREATE INDEX idx_mouvement_stock_ligne_conditionnement_ligne
    ON campement.mouvement_stock_ligne_conditionnement (mouvement_stock_ligne_id);

CREATE INDEX idx_mouvement_stock_ligne_conditionnement_conditionnement
    ON campement.mouvement_stock_ligne_conditionnement (conditionnement_id);

INSERT INTO campement.unite (nom, symbole, facteur_conversion)
VALUES
    ('gramme',      'g',  1),
    ('kilogramme',  'kg', 1000),
    ('litre',       'L',  1000),
    ('millilitre',  'mL', 1),
    ('pièce',       'pc', 1)
    ON CONFLICT DO NOTHING;

INSERT INTO campement.type_repas (code, libelle, ordre)
VALUES
    ('PETIT_DEJEUNER', 'Petit-déjeuner', 1),
    ('DEJEUNER',       'Déjeuner',       2),
    ('GOUTER',         'Goûter',         3),
    ('DINER',          'Dîner',          4)
    ON CONFLICT (code) DO NOTHING;

INSERT INTO campement.public_cible (code, libelle, ordre)
VALUES
    ('LOUVETEAUX_JEANNETTES', 'Louveteaux-Jeannettes', 1),
    ('SCOUTS_GUIDES',         'Scouts-Guides',         2),
    ('PIONNIERS_CARAVELLES',  'Pionniers-Caravelles',  3),
    ('ADULTE',                 'Adulte',                 4)
    ON CONFLICT (code) DO NOTHING;

INSERT INTO campement.type_mouvement (code, libelle, ordre)
VALUES
    ('ENTREE',     'Entrée',     1),
    ('SORTIE',     'Sortie',     2)
    ON CONFLICT (code) DO NOTHING;

INSERT INTO campement.utilisateur (email, mot_de_passe, prenom, nom, roles, actif)
VALUES
    ('saisie-consommation@campement.local', '!', 'Saisie', 'Consommation', '["ROLE_TECHNIQUE"]'::JSONB, TRUE)
    ON CONFLICT (email) DO NOTHING;
