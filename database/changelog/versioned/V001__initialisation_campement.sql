--liquibase formatted sql

--changeset campement:V001-initialisation splitStatements:false
--comment: Base initiale autonome de Campement

--
--



SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: campement; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA campement;


--
-- Name: SCHEMA campement; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA campement IS 'Schéma principal de l’application Campement';


--
-- Name: completer_type_conditionnement(); Type: FUNCTION; Schema: campement; Owner: -
--

CREATE FUNCTION campement.completer_type_conditionnement() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
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


--
-- Name: completer_unite_inventaire_denree(); Type: FUNCTION; Schema: campement; Owner: -
--

CREATE FUNCTION campement.completer_unite_inventaire_denree() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.unite_inventaire_id IS NULL THEN
        NEW.unite_inventaire_id := NEW.unite_reference_id;
    END IF;
    RETURN NEW;
END;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: audit_mouvement_stock; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.audit_mouvement_stock (
    id uuid DEFAULT uuidv7() NOT NULL,
    mouvement_stock_id uuid NOT NULL,
    sejour_id uuid NOT NULL,
    utilisateur_id uuid,
    utilisateur_libelle character varying(320) NOT NULL,
    action character varying(20) NOT NULL,
    motif text NOT NULL,
    etat_avant jsonb,
    etat_apres jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_audit_mouvement_stock_action CHECK (((action)::text = ANY ((ARRAY['MODIFICATION'::character varying, 'ANNULATION'::character varying, 'SUPPRESSION'::character varying])::text[]))),
    CONSTRAINT chk_audit_mouvement_stock_etats CHECK (((((action)::text = ANY ((ARRAY['MODIFICATION'::character varying, 'ANNULATION'::character varying])::text[])) AND (etat_avant IS NOT NULL) AND (etat_apres IS NOT NULL)) OR (((action)::text = 'SUPPRESSION'::text) AND (etat_avant IS NOT NULL) AND (etat_apres IS NULL)))),
    CONSTRAINT chk_audit_mouvement_stock_motif CHECK ((btrim(motif) <> ''::text))
);


--
-- Name: denree; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.denree (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    nom character varying(150) NOT NULL,
    unite_reference_id uuid NOT NULL,
    stock_min numeric(12,3),
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    unite_inventaire_id uuid NOT NULL,
    CONSTRAINT chk_denree_stock_min CHECK (((stock_min IS NULL) OR (stock_min >= (0)::numeric)))
);


--
-- Name: denree_fournisseur; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.denree_fournisseur (
    id uuid DEFAULT uuidv7() NOT NULL,
    fournisseur_id uuid NOT NULL,
    denree_id uuid NOT NULL,
    reference character varying(100),
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: denree_fournisseur_conditionnement; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.denree_fournisseur_conditionnement (
    id uuid DEFAULT uuidv7() NOT NULL,
    reference_fournisseur_id uuid CONSTRAINT denree_fournisseur_conditionn_reference_fournisseur_id_not_null NOT NULL,
    ordre smallint NOT NULL,
    libelle character varying(50) NOT NULL,
    quantite_contenu numeric(12,3) NOT NULL,
    libelle_contenu character varying(50),
    unite_contenu_id uuid,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    conditionnement_id uuid NOT NULL,
    CONSTRAINT chk_denree_fournisseur_conditionnement_contenu CHECK (((libelle_contenu IS NULL) <> (unite_contenu_id IS NULL))),
    CONSTRAINT chk_denree_fournisseur_conditionnement_ordre CHECK ((ordre > 0)),
    CONSTRAINT chk_denree_fournisseur_conditionnement_quantite CHECK ((quantite_contenu > (0)::numeric))
);


--
-- Name: document_participant; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.document_participant (
    id uuid DEFAULT uuidv7() NOT NULL,
    participant_id uuid NOT NULL,
    type character varying(40) NOT NULL,
    nom_fichier character varying(255) NOT NULL,
    chemin_stockage character varying(500) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_document_participant_type CHECK (((type)::text = ANY ((ARRAY['autorisation_depart_camp'::character varying, 'fiche_sanitaire'::character varying, 'vaccins'::character varying, 'qualification'::character varying])::text[])))
);


--
-- Name: fournisseur; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.fournisseur (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    nom character varying(150) NOT NULL,
    telephone character varying(30),
    email character varying(150),
    adresse text,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: groupe; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.groupe (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    nom character varying(150) NOT NULL,
    effectif_jeune integer DEFAULT 0 NOT NULL,
    effectif_adulte integer DEFAULT 0 NOT NULL,
    type character varying(30) NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    date_debut_presence date NOT NULL,
    date_fin_presence date NOT NULL,
    nombre_vegetariens integer DEFAULT 0 NOT NULL,
    nombre_sans_lactose integer DEFAULT 0 NOT NULL,
    nombre_sans_gluten integer DEFAULT 0 NOT NULL,
    CONSTRAINT chk_groupe_dates_presence CHECK ((date_fin_presence >= date_debut_presence)),
    CONSTRAINT chk_groupe_effectif_adulte CHECK ((effectif_adulte >= 0)),
    CONSTRAINT chk_groupe_effectif_jeune CHECK ((effectif_jeune >= 0)),
    CONSTRAINT chk_groupe_nombre_sans_gluten CHECK ((nombre_sans_gluten >= 0)),
    CONSTRAINT chk_groupe_nombre_sans_lactose CHECK ((nombre_sans_lactose >= 0)),
    CONSTRAINT chk_groupe_nombre_vegetariens CHECK ((nombre_vegetariens >= 0))
);


--
-- Name: groupe_repas; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.groupe_repas (
    id uuid DEFAULT uuidv7() NOT NULL,
    groupe_id uuid NOT NULL,
    menu_id uuid NOT NULL,
    mode character varying(20) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT ck_groupe_repas_mode CHECK (((mode)::text = ANY ((ARRAY['EXPLO'::character varying, 'PIQUE_NIQUE_1'::character varying, 'PIQUE_NIQUE_2'::character varying, 'NON_PRIS'::character varying])::text[])))
);


--
-- Name: menu; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.menu (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    sejour_type_repas_id uuid,
    date_menu date,
    nom character varying(150),
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    special_code character varying(20),
    CONSTRAINT chk_menu_identite CHECK ((((special_code IS NULL) AND (date_menu IS NOT NULL) AND (sejour_type_repas_id IS NOT NULL)) OR (((special_code)::text = ANY ((ARRAY['EXPLO'::character varying, 'PIQUE_NIQUE_1'::character varying, 'PIQUE_NIQUE_2'::character varying])::text[])) AND (date_menu IS NULL) AND (sejour_type_repas_id IS NULL))))
);


--
-- Name: menu_denree; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.menu_denree (
    id uuid DEFAULT uuidv7() NOT NULL,
    menu_id uuid NOT NULL,
    denree_id uuid NOT NULL,
    ordre smallint DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    conditionnement_id uuid NOT NULL,
    categorie character varying(20),
    recette_id uuid,
    recette_instance_id uuid,
    regime character varying(20),
    CONSTRAINT chk_menu_denree_categorie CHECK (((categorie IS NULL) OR ((categorie)::text = ANY ((ARRAY['ENTREE'::character varying, 'PLAT'::character varying, 'FROMAGE'::character varying, 'DESSERT'::character varying])::text[])))),
    CONSTRAINT chk_menu_denree_ordre CHECK ((ordre >= 0)),
    CONSTRAINT chk_menu_denree_recette_instance CHECK (((recette_id IS NULL) = (recette_instance_id IS NULL))),
    CONSTRAINT chk_menu_denree_regime CHECK (((regime IS NULL) OR ((regime)::text = ANY ((ARRAY['VEGETARIEN'::character varying, 'SANS_LACTOSE'::character varying, 'SANS_GLUTEN'::character varying])::text[]))))
);


--
-- Name: menu_denree_quantite; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.menu_denree_quantite (
    id uuid DEFAULT uuidv7() NOT NULL,
    menu_denree_id uuid NOT NULL,
    sejour_public_cible_id uuid NOT NULL,
    quantite_individuelle numeric(12,3) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_menu_denree_quantite_quantite CHECK ((quantite_individuelle >= (0)::numeric))
);


--
-- Name: mouvement_stock; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.mouvement_stock (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    utilisateur_id uuid NOT NULL,
    groupe_id uuid,
    menu_id uuid,
    type_mouvement_id uuid NOT NULL,
    origine_mouvement_id uuid NOT NULL,
    date_mouvement timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    cle_soumission uuid,
    annule_at timestamp with time zone,
    annule_par_id uuid,
    motif_annulation text,
    CONSTRAINT chk_mouvement_stock_annulation CHECK ((((annule_at IS NULL) AND (annule_par_id IS NULL) AND (motif_annulation IS NULL)) OR ((annule_at IS NOT NULL) AND (motif_annulation IS NOT NULL) AND (btrim(motif_annulation) <> ''::text))))
);


--
-- Name: mouvement_stock_ligne; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.mouvement_stock_ligne (
    id uuid DEFAULT uuidv7() NOT NULL,
    mouvement_stock_id uuid NOT NULL,
    denree_id uuid NOT NULL,
    reference_fournisseur_id uuid,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    conditionnement_saisie_id uuid,
    numero_lot character varying(100),
    quantite_saisie numeric(12,3),
    CONSTRAINT chk_mouvement_stock_ligne_quantite_saisie CHECK (((quantite_saisie IS NULL) OR (quantite_saisie > (0)::numeric))),
    CONSTRAINT chk_mouvement_stock_ligne_stockage_natif CHECK ((((reference_fournisseur_id IS NULL) AND (conditionnement_saisie_id IS NOT NULL) AND (quantite_saisie IS NOT NULL)) OR ((reference_fournisseur_id IS NOT NULL) AND (conditionnement_saisie_id IS NULL) AND (quantite_saisie IS NULL))))
);


--
-- Name: COLUMN mouvement_stock_ligne.numero_lot; Type: COMMENT; Schema: campement; Owner: -
--

COMMENT ON COLUMN campement.mouvement_stock_ligne.numero_lot IS 'Numéro de lot relevé sur la denrée lors de son entrée en stock.';


--
-- Name: COLUMN mouvement_stock_ligne.quantite_saisie; Type: COMMENT; Schema: campement; Owner: -
--

COMMENT ON COLUMN campement.mouvement_stock_ligne.quantite_saisie IS 'Quantité brute saisie par l’utilisateur dans conditionnement_saisie_id ; NULL pour une ligne détaillée par niveaux fournisseur.';


--
-- Name: mouvement_stock_ligne_conditionnement; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.mouvement_stock_ligne_conditionnement (
    id uuid DEFAULT uuidv7() NOT NULL,
    mouvement_stock_ligne_id uuid CONSTRAINT mouvement_stock_ligne_conditi_mouvement_stock_ligne_id_not_null NOT NULL,
    conditionnement_id uuid CONSTRAINT mouvement_stock_ligne_conditionneme_conditionnement_id_not_null NOT NULL,
    quantite numeric(12,3) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_mouvement_stock_ligne_conditionnement_quantite CHECK ((quantite > (0)::numeric))
);


--
-- Name: origine_mouvement; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.origine_mouvement (
    id uuid DEFAULT uuidv7() NOT NULL,
    code character varying(50) NOT NULL,
    libelle character varying(150) NOT NULL,
    ordre smallint DEFAULT 0 NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_origine_mouvement_ordre CHECK ((ordre >= 0))
);


--
-- Name: participant; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.participant (
    id uuid DEFAULT uuidv7() NOT NULL,
    groupe_id uuid NOT NULL,
    type character varying(10) NOT NULL,
    nom character varying(150) NOT NULL,
    prenom character varying(150) NOT NULL,
    date_naissance date NOT NULL,
    telephone_parent_1 character varying(30),
    telephone_parent_2 character varying(30),
    email_parents character varying(254),
    qualifications jsonb DEFAULT '[]'::jsonb NOT NULL,
    autre_diplome character varying(255),
    stagiaire_bafa boolean DEFAULT false NOT NULL,
    date_debut_presence date NOT NULL,
    date_fin_presence date NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    contact_urgence_nom_prenom character varying(300),
    contact_urgence_telephone character varying(30),
    telephone character varying(30),
    email character varying(254),
    CONSTRAINT chk_participant_contacts CHECK (((((type)::text = 'jeune'::text) AND (telephone_parent_1 IS NOT NULL) AND (email_parents IS NOT NULL) AND (contact_urgence_nom_prenom IS NULL) AND (contact_urgence_telephone IS NULL)) OR (((type)::text = 'adulte'::text) AND (contact_urgence_nom_prenom IS NOT NULL) AND (contact_urgence_telephone IS NOT NULL) AND (telephone_parent_1 IS NULL) AND (telephone_parent_2 IS NULL) AND (email_parents IS NULL)))),
    CONSTRAINT chk_participant_dates CHECK ((date_fin_presence >= date_debut_presence)),
    CONSTRAINT chk_participant_type CHECK (((type)::text = ANY ((ARRAY['jeune'::character varying, 'adulte'::character varying])::text[])))
);


--
-- Name: presence_participant; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.presence_participant (
    id uuid DEFAULT uuidv7() NOT NULL,
    participant_id uuid NOT NULL,
    date_presence date NOT NULL,
    statut character varying(10) NOT NULL,
    commentaire character varying(500),
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_presence_depart_commentaire CHECK ((((statut)::text <> 'depart'::text) OR (commentaire IS NOT NULL))),
    CONSTRAINT chk_presence_participant_statut CHECK (((statut)::text = ANY ((ARRAY['absent'::character varying, 'depart'::character varying])::text[])))
);


--
-- Name: public_cible; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.public_cible (
    id uuid DEFAULT uuidv7() NOT NULL,
    code character varying(50) NOT NULL,
    libelle character varying(150) NOT NULL,
    ordre smallint DEFAULT 0 NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_public_cible_ordre CHECK ((ordre >= 0))
);


--
-- Name: recette; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.recette (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    nom character varying(150) NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    categorie character varying(20) DEFAULT 'PLAT'::character varying NOT NULL,
    CONSTRAINT chk_recette_categorie CHECK (((categorie)::text = ANY ((ARRAY['PETIT_DEJEUNER'::character varying, 'ENTREE'::character varying, 'PLAT'::character varying, 'FROMAGE'::character varying, 'DESSERT'::character varying, 'GOUTER'::character varying])::text[])))
);


--
-- Name: recette_denree; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.recette_denree (
    id uuid DEFAULT uuidv7() NOT NULL,
    recette_id uuid NOT NULL,
    denree_id uuid NOT NULL,
    conditionnement_id uuid NOT NULL,
    ordre smallint DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    regime character varying(20),
    CONSTRAINT chk_recette_denree_regime CHECK (((regime IS NULL) OR ((regime)::text = ANY ((ARRAY['VEGETARIEN'::character varying, 'SANS_LACTOSE'::character varying, 'SANS_GLUTEN'::character varying])::text[]))))
);


--
-- Name: recette_denree_quantite; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.recette_denree_quantite (
    id uuid DEFAULT uuidv7() NOT NULL,
    recette_denree_id uuid NOT NULL,
    sejour_public_cible_id uuid NOT NULL,
    quantite_individuelle numeric(12,3) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_recette_denree_quantite CHECK ((quantite_individuelle >= (0)::numeric))
);


--
-- Name: sejour; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.sejour (
    id uuid DEFAULT uuidv7() NOT NULL,
    nom character varying(150) NOT NULL,
    date_debut date NOT NULL,
    date_fin date NOT NULL,
    lieu character varying(150),
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    module_intendance_actif boolean DEFAULT true NOT NULL,
    module_administratif_actif boolean DEFAULT true NOT NULL,
    distribution_publique_active boolean DEFAULT true NOT NULL,
    jeton_distribution_publique uuid DEFAULT uuidv7() NOT NULL,
    distribuer_gouter_dejeuner boolean DEFAULT false NOT NULL,
    module_situations_particulieres_actif boolean DEFAULT false NOT NULL,
    anonymise_at timestamp with time zone,
    CONSTRAINT chk_sejour_dates CHECK ((date_fin >= date_debut))
);


--
-- Name: sejour_public_cible; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.sejour_public_cible (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    public_cible_id uuid NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: sejour_type_repas; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.sejour_type_repas (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    type_repas_id uuid NOT NULL,
    distribution_active boolean DEFAULT true NOT NULL,
    ordre smallint DEFAULT 0 NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_sejour_type_repas_ordre CHECK ((ordre >= 0))
);


--
-- Name: situation_particuliere; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.situation_particuliere (
    id uuid DEFAULT uuidv7() NOT NULL,
    sejour_id uuid NOT NULL,
    libelle character varying(200) NOT NULL,
    date_situation date NOT NULL,
    informations_complementaires jsonb DEFAULT '[]'::jsonb NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_situation_particuliere_informations CHECK ((jsonb_typeof(informations_complementaires) = 'array'::text)),
    CONSTRAINT chk_situation_particuliere_libelle CHECK ((btrim((libelle)::text) <> ''::text))
);


--
-- Name: situation_particuliere_participant; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.situation_particuliere_participant (
    situation_particuliere_id uuid CONSTRAINT situation_particuliere_parti_situation_particuliere_id_not_null NOT NULL,
    participant_id uuid NOT NULL
);


--
-- Name: tache_situation_particuliere; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.tache_situation_particuliere (
    id uuid DEFAULT uuidv7() NOT NULL,
    situation_particuliere_id uuid NOT NULL,
    type_predefini character varying(40),
    libelle_libre character varying(200),
    origine character varying(25) NOT NULL,
    statut character varying(15) DEFAULT 'A_FAIRE'::character varying NOT NULL,
    date_echeance date,
    date_realisation date,
    commentaire text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_tache_situation_particuliere_libelle CHECK ((((type_predefini IS NOT NULL) AND (libelle_libre IS NULL)) OR ((type_predefini IS NULL) AND ((origine)::text = 'LIBRE'::text) AND (btrim((libelle_libre)::text) <> ''::text)))),
    CONSTRAINT chk_tache_situation_particuliere_origine CHECK (((origine)::text = ANY ((ARRAY['AUTOMATIQUE'::character varying, 'MANUELLE_PREDEFINIE'::character varying, 'LIBRE'::character varying])::text[]))),
    CONSTRAINT chk_tache_situation_particuliere_realisation CHECK (((((statut)::text = 'REALISE'::text) AND (date_realisation IS NOT NULL)) OR (((statut)::text <> 'REALISE'::text) AND (date_realisation IS NULL)))),
    CONSTRAINT chk_tache_situation_particuliere_statut CHECK (((statut)::text = ANY ((ARRAY['A_FAIRE'::character varying, 'REALISE'::character varying, 'NON_REQUIS'::character varying])::text[]))),
    CONSTRAINT chk_tache_situation_particuliere_type CHECK (((type_predefini IS NULL) OR ((type_predefini)::text = ANY ((ARRAY['DECLARATION_ACCIDENT_SGDF'::character varying, 'DECLARATION_EVENEMENT_GRAVE'::character varying, 'IP_OU_SIGNALEMENT'::character varying, 'APPEL_LIGNE_URGENCE'::character varying])::text[]))))
);


--
-- Name: type_mouvement; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.type_mouvement (
    id uuid DEFAULT uuidv7() NOT NULL,
    code character varying(50) NOT NULL,
    libelle character varying(150) NOT NULL,
    ordre smallint DEFAULT 0 NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_type_mouvement_ordre CHECK ((ordre >= 0))
);


--
-- Name: type_repas; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.type_repas (
    id uuid DEFAULT uuidv7() NOT NULL,
    code character varying(50) NOT NULL,
    libelle character varying(150) NOT NULL,
    ordre smallint DEFAULT 0 NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_type_repas_ordre CHECK ((ordre >= 0))
);


--
-- Name: unite; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.unite (
    id uuid DEFAULT uuidv7() NOT NULL,
    nom character varying(50) NOT NULL,
    symbole character varying(10) NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    utilisable_conditionnement boolean DEFAULT true NOT NULL
);


--
-- Name: utilisateur; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.utilisateur (
    id uuid DEFAULT uuidv7() NOT NULL,
    groupe_id uuid,
    email character varying(180) NOT NULL,
    mot_de_passe character varying(255) NOT NULL,
    prenom character varying(100) NOT NULL,
    nom character varying(100) NOT NULL,
    roles jsonb NOT NULL,
    actif boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP(0) NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP(0) NOT NULL,
    changement_mot_de_passe_requis boolean DEFAULT false NOT NULL,
    jeton_reinitialisation character varying(64),
    expiration_jeton_reinitialisation timestamp with time zone,
    dernier_sejour_id uuid,
    desactive_at timestamp with time zone,
    CONSTRAINT chk_utilisateur_affectation_role CHECK (((((roles ->> 0) = 'ROLE_GESTIONNAIRE'::text) AND (groupe_id IS NULL)) OR (((roles ->> 0) = 'ROLE_GROUPE'::text) AND (groupe_id IS NOT NULL)) OR (((roles ->> 0) = ANY (ARRAY['ROLE_ADMIN'::text, 'ROLE_TECHNIQUE'::text])) AND (groupe_id IS NULL)))),
    CONSTRAINT chk_utilisateur_roles CHECK (((jsonb_typeof(roles) = 'array'::text) AND (jsonb_array_length(roles) = 1) AND ((roles ->> 0) = ANY (ARRAY['ROLE_GESTIONNAIRE'::text, 'ROLE_GROUPE'::text, 'ROLE_ADMIN'::text, 'ROLE_TECHNIQUE'::text]))))
);


--
-- Name: utilisateur_sejour; Type: TABLE; Schema: campement; Owner: -
--

CREATE TABLE campement.utilisateur_sejour (
    utilisateur_id uuid NOT NULL,
    sejour_id uuid NOT NULL
);


--
-- Name: audit_mouvement_stock pk_audit_mouvement_stock; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.audit_mouvement_stock
    ADD CONSTRAINT pk_audit_mouvement_stock PRIMARY KEY (id);


--
-- Name: denree pk_denree; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree
    ADD CONSTRAINT pk_denree PRIMARY KEY (id);


--
-- Name: denree_fournisseur pk_denree_fournisseur; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur
    ADD CONSTRAINT pk_denree_fournisseur PRIMARY KEY (id);


--
-- Name: denree_fournisseur_conditionnement pk_denree_fournisseur_conditionnement; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur_conditionnement
    ADD CONSTRAINT pk_denree_fournisseur_conditionnement PRIMARY KEY (id);


--
-- Name: document_participant pk_document_participant; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.document_participant
    ADD CONSTRAINT pk_document_participant PRIMARY KEY (id);


--
-- Name: fournisseur pk_fournisseur; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.fournisseur
    ADD CONSTRAINT pk_fournisseur PRIMARY KEY (id);


--
-- Name: groupe pk_groupe; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.groupe
    ADD CONSTRAINT pk_groupe PRIMARY KEY (id);


--
-- Name: groupe_repas pk_groupe_repas; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.groupe_repas
    ADD CONSTRAINT pk_groupe_repas PRIMARY KEY (id);


--
-- Name: menu pk_menu; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu
    ADD CONSTRAINT pk_menu PRIMARY KEY (id);


--
-- Name: menu_denree pk_menu_denree; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree
    ADD CONSTRAINT pk_menu_denree PRIMARY KEY (id);


--
-- Name: menu_denree_quantite pk_menu_denree_quantite; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree_quantite
    ADD CONSTRAINT pk_menu_denree_quantite PRIMARY KEY (id);


--
-- Name: mouvement_stock pk_mouvement_stock; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT pk_mouvement_stock PRIMARY KEY (id);


--
-- Name: mouvement_stock_ligne pk_mouvement_stock_ligne; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne
    ADD CONSTRAINT pk_mouvement_stock_ligne PRIMARY KEY (id);


--
-- Name: mouvement_stock_ligne_conditionnement pk_mouvement_stock_ligne_conditionnement; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne_conditionnement
    ADD CONSTRAINT pk_mouvement_stock_ligne_conditionnement PRIMARY KEY (id);


--
-- Name: origine_mouvement pk_origine_mouvement; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.origine_mouvement
    ADD CONSTRAINT pk_origine_mouvement PRIMARY KEY (id);


--
-- Name: participant pk_participant; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.participant
    ADD CONSTRAINT pk_participant PRIMARY KEY (id);


--
-- Name: presence_participant pk_presence_participant; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.presence_participant
    ADD CONSTRAINT pk_presence_participant PRIMARY KEY (id);


--
-- Name: public_cible pk_public_cible; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.public_cible
    ADD CONSTRAINT pk_public_cible PRIMARY KEY (id);


--
-- Name: recette pk_recette; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette
    ADD CONSTRAINT pk_recette PRIMARY KEY (id);


--
-- Name: recette_denree pk_recette_denree; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette_denree
    ADD CONSTRAINT pk_recette_denree PRIMARY KEY (id);


--
-- Name: recette_denree_quantite pk_recette_denree_quantite; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette_denree_quantite
    ADD CONSTRAINT pk_recette_denree_quantite PRIMARY KEY (id);


--
-- Name: sejour pk_sejour; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour
    ADD CONSTRAINT pk_sejour PRIMARY KEY (id);


--
-- Name: sejour_public_cible pk_sejour_public_cible; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour_public_cible
    ADD CONSTRAINT pk_sejour_public_cible PRIMARY KEY (id);


--
-- Name: sejour_type_repas pk_sejour_type_repas; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour_type_repas
    ADD CONSTRAINT pk_sejour_type_repas PRIMARY KEY (id);


--
-- Name: situation_particuliere pk_situation_particuliere; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.situation_particuliere
    ADD CONSTRAINT pk_situation_particuliere PRIMARY KEY (id);


--
-- Name: situation_particuliere_participant pk_situation_particuliere_participant; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.situation_particuliere_participant
    ADD CONSTRAINT pk_situation_particuliere_participant PRIMARY KEY (situation_particuliere_id, participant_id);


--
-- Name: tache_situation_particuliere pk_tache_situation_particuliere; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.tache_situation_particuliere
    ADD CONSTRAINT pk_tache_situation_particuliere PRIMARY KEY (id);


--
-- Name: type_mouvement pk_type_mouvement; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.type_mouvement
    ADD CONSTRAINT pk_type_mouvement PRIMARY KEY (id);


--
-- Name: type_repas pk_type_repas; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.type_repas
    ADD CONSTRAINT pk_type_repas PRIMARY KEY (id);


--
-- Name: unite pk_unite; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.unite
    ADD CONSTRAINT pk_unite PRIMARY KEY (id);


--
-- Name: utilisateur pk_utilisateur; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.utilisateur
    ADD CONSTRAINT pk_utilisateur PRIMARY KEY (id);


--
-- Name: utilisateur_sejour pk_utilisateur_sejour; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.utilisateur_sejour
    ADD CONSTRAINT pk_utilisateur_sejour PRIMARY KEY (utilisateur_id, sejour_id);


--
-- Name: denree_fournisseur uq_denree_fournisseur; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur
    ADD CONSTRAINT uq_denree_fournisseur UNIQUE (fournisseur_id, reference);


--
-- Name: denree_fournisseur_conditionnement uq_denree_fournisseur_conditionnement; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur_conditionnement
    ADD CONSTRAINT uq_denree_fournisseur_conditionnement UNIQUE (reference_fournisseur_id, ordre) DEFERRABLE INITIALLY DEFERRED;


--
-- Name: denree uq_denree_nom; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree
    ADD CONSTRAINT uq_denree_nom UNIQUE (sejour_id, nom);


--
-- Name: fournisseur uq_fournisseur_nom; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.fournisseur
    ADD CONSTRAINT uq_fournisseur_nom UNIQUE (sejour_id, nom);


--
-- Name: groupe_repas uq_groupe_repas; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.groupe_repas
    ADD CONSTRAINT uq_groupe_repas UNIQUE (groupe_id, menu_id);


--
-- Name: groupe uq_groupe_sejour_nom; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.groupe
    ADD CONSTRAINT uq_groupe_sejour_nom UNIQUE (sejour_id, nom);


--
-- Name: menu_denree_quantite uq_menu_denree_quantite; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree_quantite
    ADD CONSTRAINT uq_menu_denree_quantite UNIQUE (menu_denree_id, sejour_public_cible_id);


--
-- Name: mouvement_stock uq_mouvement_stock_cle_soumission; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT uq_mouvement_stock_cle_soumission UNIQUE (cle_soumission);


--
-- Name: mouvement_stock_ligne_conditionnement uq_mouvement_stock_ligne_conditionnement; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne_conditionnement
    ADD CONSTRAINT uq_mouvement_stock_ligne_conditionnement UNIQUE (mouvement_stock_ligne_id, conditionnement_id);


--
-- Name: mouvement_stock_ligne uq_mouvement_stock_ligne_denree; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne
    ADD CONSTRAINT uq_mouvement_stock_ligne_denree UNIQUE (mouvement_stock_id, denree_id);


--
-- Name: origine_mouvement uq_origine_mouvement_code; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.origine_mouvement
    ADD CONSTRAINT uq_origine_mouvement_code UNIQUE (code);


--
-- Name: presence_participant uq_presence_participant_date; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.presence_participant
    ADD CONSTRAINT uq_presence_participant_date UNIQUE (participant_id, date_presence);


--
-- Name: public_cible uq_public_cible_code; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.public_cible
    ADD CONSTRAINT uq_public_cible_code UNIQUE (code);


--
-- Name: recette_denree_quantite uq_recette_denree_quantite; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette_denree_quantite
    ADD CONSTRAINT uq_recette_denree_quantite UNIQUE (recette_denree_id, sejour_public_cible_id);


--
-- Name: recette uq_recette_nom; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette
    ADD CONSTRAINT uq_recette_nom UNIQUE (sejour_id, nom);


--
-- Name: sejour uq_sejour_jeton_distribution_publique; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour
    ADD CONSTRAINT uq_sejour_jeton_distribution_publique UNIQUE (jeton_distribution_publique);


--
-- Name: sejour_public_cible uq_sejour_public_cible; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour_public_cible
    ADD CONSTRAINT uq_sejour_public_cible UNIQUE (sejour_id, public_cible_id);


--
-- Name: sejour_type_repas uq_sejour_type_repas; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour_type_repas
    ADD CONSTRAINT uq_sejour_type_repas UNIQUE (sejour_id, type_repas_id);


--
-- Name: tache_situation_particuliere uq_tache_situation_particuliere_type; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.tache_situation_particuliere
    ADD CONSTRAINT uq_tache_situation_particuliere_type UNIQUE (situation_particuliere_id, type_predefini);


--
-- Name: type_mouvement uq_type_mouvement_code; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.type_mouvement
    ADD CONSTRAINT uq_type_mouvement_code UNIQUE (code);


--
-- Name: type_repas uq_type_repas_code; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.type_repas
    ADD CONSTRAINT uq_type_repas_code UNIQUE (code);


--
-- Name: unite uq_unite_nom; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.unite
    ADD CONSTRAINT uq_unite_nom UNIQUE (nom);


--
-- Name: unite uq_unite_symbole; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.unite
    ADD CONSTRAINT uq_unite_symbole UNIQUE (symbole);


--
-- Name: utilisateur uq_utilisateur_email; Type: CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.utilisateur
    ADD CONSTRAINT uq_utilisateur_email UNIQUE (email);


--
-- Name: idx_audit_mouvement_stock_mouvement; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_audit_mouvement_stock_mouvement ON campement.audit_mouvement_stock USING btree (mouvement_stock_id);


--
-- Name: idx_audit_mouvement_stock_sejour_date; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_audit_mouvement_stock_sejour_date ON campement.audit_mouvement_stock USING btree (sejour_id, created_at DESC);


--
-- Name: idx_audit_mouvement_stock_utilisateur; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_audit_mouvement_stock_utilisateur ON campement.audit_mouvement_stock USING btree (utilisateur_id);


--
-- Name: idx_denree_fournisseur_conditionnement_reference; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_denree_fournisseur_conditionnement_reference ON campement.denree_fournisseur_conditionnement USING btree (reference_fournisseur_id);


--
-- Name: idx_denree_fournisseur_conditionnement_type; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_denree_fournisseur_conditionnement_type ON campement.denree_fournisseur_conditionnement USING btree (conditionnement_id);


--
-- Name: idx_denree_fournisseur_conditionnement_unite; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_denree_fournisseur_conditionnement_unite ON campement.denree_fournisseur_conditionnement USING btree (unite_contenu_id);


--
-- Name: idx_denree_fournisseur_denree; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_denree_fournisseur_denree ON campement.denree_fournisseur USING btree (denree_id);


--
-- Name: idx_denree_fournisseur_fournisseur; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_denree_fournisseur_fournisseur ON campement.denree_fournisseur USING btree (fournisseur_id);


--
-- Name: idx_denree_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_denree_sejour ON campement.denree USING btree (sejour_id);


--
-- Name: idx_denree_unite; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_denree_unite ON campement.denree USING btree (unite_reference_id);


--
-- Name: idx_denree_unite_inventaire; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_denree_unite_inventaire ON campement.denree USING btree (unite_inventaire_id);


--
-- Name: idx_document_participant; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_document_participant ON campement.document_participant USING btree (participant_id);


--
-- Name: idx_fournisseur_nom; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_fournisseur_nom ON campement.fournisseur USING btree (nom);


--
-- Name: idx_fournisseur_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_fournisseur_sejour ON campement.fournisseur USING btree (sejour_id);


--
-- Name: idx_groupe_presence; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_groupe_presence ON campement.groupe USING btree (sejour_id, date_debut_presence, date_fin_presence) WHERE (actif = true);


--
-- Name: idx_groupe_repas_groupe; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_groupe_repas_groupe ON campement.groupe_repas USING btree (groupe_id);


--
-- Name: idx_groupe_repas_menu; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_groupe_repas_menu ON campement.groupe_repas USING btree (menu_id);


--
-- Name: idx_groupe_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_groupe_sejour ON campement.groupe USING btree (sejour_id);


--
-- Name: idx_menu_date; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_date ON campement.menu USING btree (date_menu);


--
-- Name: idx_menu_denree_conditionnement; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_denree_conditionnement ON campement.menu_denree USING btree (conditionnement_id);


--
-- Name: idx_menu_denree_denree; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_denree_denree ON campement.menu_denree USING btree (denree_id);


--
-- Name: idx_menu_denree_menu; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_denree_menu ON campement.menu_denree USING btree (menu_id);


--
-- Name: idx_menu_denree_quantite_menu_denree; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_denree_quantite_menu_denree ON campement.menu_denree_quantite USING btree (menu_denree_id);


--
-- Name: idx_menu_denree_quantite_sejour_public_cible; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_denree_quantite_sejour_public_cible ON campement.menu_denree_quantite USING btree (sejour_public_cible_id);


--
-- Name: idx_menu_denree_recette; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_denree_recette ON campement.menu_denree USING btree (recette_id);


--
-- Name: idx_menu_denree_recette_instance; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_denree_recette_instance ON campement.menu_denree USING btree (recette_instance_id);


--
-- Name: idx_menu_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_sejour ON campement.menu USING btree (sejour_id);


--
-- Name: idx_menu_sejour_type_repas; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_menu_sejour_type_repas ON campement.menu USING btree (sejour_type_repas_id);


--
-- Name: idx_mouvement_stock_annule; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_annule ON campement.mouvement_stock USING btree (annule_at);


--
-- Name: idx_mouvement_stock_annule_par; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_annule_par ON campement.mouvement_stock USING btree (annule_par_id);


--
-- Name: idx_mouvement_stock_date; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_date ON campement.mouvement_stock USING btree (date_mouvement);


--
-- Name: idx_mouvement_stock_groupe; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_groupe ON campement.mouvement_stock USING btree (groupe_id);


--
-- Name: idx_mouvement_stock_ligne_conditionnement_conditionnement; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_ligne_conditionnement_conditionnement ON campement.mouvement_stock_ligne_conditionnement USING btree (conditionnement_id);


--
-- Name: idx_mouvement_stock_ligne_conditionnement_ligne; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_ligne_conditionnement_ligne ON campement.mouvement_stock_ligne_conditionnement USING btree (mouvement_stock_ligne_id);


--
-- Name: idx_mouvement_stock_ligne_conditionnement_saisie; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_ligne_conditionnement_saisie ON campement.mouvement_stock_ligne USING btree (conditionnement_saisie_id);


--
-- Name: idx_mouvement_stock_ligne_denree; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_ligne_denree ON campement.mouvement_stock_ligne USING btree (denree_id);


--
-- Name: idx_mouvement_stock_ligne_mouvement; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_ligne_mouvement ON campement.mouvement_stock_ligne USING btree (mouvement_stock_id);


--
-- Name: idx_mouvement_stock_ligne_reference_fournisseur; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_ligne_reference_fournisseur ON campement.mouvement_stock_ligne USING btree (reference_fournisseur_id);


--
-- Name: idx_mouvement_stock_menu; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_menu ON campement.mouvement_stock USING btree (menu_id);


--
-- Name: idx_mouvement_stock_origine; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_origine ON campement.mouvement_stock USING btree (origine_mouvement_id);


--
-- Name: idx_mouvement_stock_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_sejour ON campement.mouvement_stock USING btree (sejour_id);


--
-- Name: idx_mouvement_stock_type; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_type ON campement.mouvement_stock USING btree (type_mouvement_id);


--
-- Name: idx_mouvement_stock_utilisateur; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_mouvement_stock_utilisateur ON campement.mouvement_stock USING btree (utilisateur_id);


--
-- Name: idx_participant_groupe; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_participant_groupe ON campement.participant USING btree (groupe_id);


--
-- Name: idx_participant_groupe_type; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_participant_groupe_type ON campement.participant USING btree (groupe_id, type);


--
-- Name: idx_presence_participant_date; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_presence_participant_date ON campement.presence_participant USING btree (date_presence);


--
-- Name: idx_recette_denree_recette; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_recette_denree_recette ON campement.recette_denree USING btree (recette_id);


--
-- Name: idx_recette_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_recette_sejour ON campement.recette USING btree (sejour_id);


--
-- Name: idx_sejour_anonymisation; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_sejour_anonymisation ON campement.sejour USING btree (date_fin, anonymise_at);


--
-- Name: idx_sejour_date; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_sejour_date ON campement.sejour USING btree (date_debut);


--
-- Name: idx_sejour_public_cible_public_cible; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_sejour_public_cible_public_cible ON campement.sejour_public_cible USING btree (public_cible_id);


--
-- Name: idx_sejour_public_cible_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_sejour_public_cible_sejour ON campement.sejour_public_cible USING btree (sejour_id);


--
-- Name: idx_sejour_type_repas_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_sejour_type_repas_sejour ON campement.sejour_type_repas USING btree (sejour_id);


--
-- Name: idx_sejour_type_repas_type_repas; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_sejour_type_repas_type_repas ON campement.sejour_type_repas USING btree (type_repas_id);


--
-- Name: idx_situation_particuliere_participant_participant; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_situation_particuliere_participant_participant ON campement.situation_particuliere_participant USING btree (participant_id);


--
-- Name: idx_situation_particuliere_sejour_date; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_situation_particuliere_sejour_date ON campement.situation_particuliere USING btree (sejour_id, date_situation DESC);


--
-- Name: idx_tache_situation_particuliere_situation; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_tache_situation_particuliere_situation ON campement.tache_situation_particuliere USING btree (situation_particuliere_id);


--
-- Name: idx_tache_situation_particuliere_statut_echeance; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_tache_situation_particuliere_statut_echeance ON campement.tache_situation_particuliere USING btree (statut, date_echeance);


--
-- Name: idx_utilisateur_dernier_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_utilisateur_dernier_sejour ON campement.utilisateur USING btree (dernier_sejour_id);


--
-- Name: idx_utilisateur_groupe; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_utilisateur_groupe ON campement.utilisateur USING btree (groupe_id);


--
-- Name: idx_utilisateur_jeton_reinitialisation; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_utilisateur_jeton_reinitialisation ON campement.utilisateur USING btree (jeton_reinitialisation);


--
-- Name: idx_utilisateur_purge; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_utilisateur_purge ON campement.utilisateur USING btree (desactive_at) WHERE (actif = false);


--
-- Name: idx_utilisateur_sejour_sejour; Type: INDEX; Schema: campement; Owner: -
--

CREATE INDEX idx_utilisateur_sejour_sejour ON campement.utilisateur_sejour USING btree (sejour_id);


--
-- Name: uq_document_participant_autorisation; Type: INDEX; Schema: campement; Owner: -
--

CREATE UNIQUE INDEX uq_document_participant_autorisation ON campement.document_participant USING btree (participant_id) WHERE ((type)::text = 'autorisation_depart_camp'::text);


--
-- Name: uq_menu_sejour_date_type; Type: INDEX; Schema: campement; Owner: -
--

CREATE UNIQUE INDEX uq_menu_sejour_date_type ON campement.menu USING btree (sejour_id, date_menu, sejour_type_repas_id) WHERE (special_code IS NULL);


--
-- Name: uq_menu_sejour_special; Type: INDEX; Schema: campement; Owner: -
--

CREATE UNIQUE INDEX uq_menu_sejour_special ON campement.menu USING btree (sejour_id, special_code) WHERE (special_code IS NOT NULL);


--
-- Name: denree_fournisseur_conditionnement trg_conditionnement_type; Type: TRIGGER; Schema: campement; Owner: -
--

CREATE TRIGGER trg_conditionnement_type BEFORE INSERT ON campement.denree_fournisseur_conditionnement FOR EACH ROW EXECUTE FUNCTION campement.completer_type_conditionnement();


--
-- Name: denree trg_denree_unite_inventaire; Type: TRIGGER; Schema: campement; Owner: -
--

CREATE TRIGGER trg_denree_unite_inventaire BEFORE INSERT ON campement.denree FOR EACH ROW EXECUTE FUNCTION campement.completer_unite_inventaire_denree();


--
-- Name: audit_mouvement_stock fk_audit_mouvement_stock_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.audit_mouvement_stock
    ADD CONSTRAINT fk_audit_mouvement_stock_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE RESTRICT;


--
-- Name: audit_mouvement_stock fk_audit_mouvement_stock_utilisateur; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.audit_mouvement_stock
    ADD CONSTRAINT fk_audit_mouvement_stock_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES campement.utilisateur(id) ON DELETE SET NULL;


--
-- Name: denree_fournisseur_conditionnement fk_denree_fournisseur_conditionnement_reference; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur_conditionnement
    ADD CONSTRAINT fk_denree_fournisseur_conditionnement_reference FOREIGN KEY (reference_fournisseur_id) REFERENCES campement.denree_fournisseur(id) ON DELETE CASCADE;


--
-- Name: denree_fournisseur_conditionnement fk_denree_fournisseur_conditionnement_type; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur_conditionnement
    ADD CONSTRAINT fk_denree_fournisseur_conditionnement_type FOREIGN KEY (conditionnement_id) REFERENCES campement.unite(id) ON DELETE RESTRICT;


--
-- Name: denree_fournisseur_conditionnement fk_denree_fournisseur_conditionnement_unite; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur_conditionnement
    ADD CONSTRAINT fk_denree_fournisseur_conditionnement_unite FOREIGN KEY (unite_contenu_id) REFERENCES campement.unite(id) ON DELETE RESTRICT;


--
-- Name: denree_fournisseur fk_denree_fournisseur_denree; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur
    ADD CONSTRAINT fk_denree_fournisseur_denree FOREIGN KEY (denree_id) REFERENCES campement.denree(id) ON DELETE RESTRICT;


--
-- Name: denree_fournisseur fk_denree_fournisseur_fournisseur; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree_fournisseur
    ADD CONSTRAINT fk_denree_fournisseur_fournisseur FOREIGN KEY (fournisseur_id) REFERENCES campement.fournisseur(id) ON DELETE RESTRICT;


--
-- Name: denree fk_denree_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree
    ADD CONSTRAINT fk_denree_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: denree fk_denree_unite; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree
    ADD CONSTRAINT fk_denree_unite FOREIGN KEY (unite_reference_id) REFERENCES campement.unite(id) ON DELETE RESTRICT;


--
-- Name: denree fk_denree_unite_inventaire; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.denree
    ADD CONSTRAINT fk_denree_unite_inventaire FOREIGN KEY (unite_inventaire_id) REFERENCES campement.unite(id) ON DELETE RESTRICT;


--
-- Name: document_participant fk_document_participant; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.document_participant
    ADD CONSTRAINT fk_document_participant FOREIGN KEY (participant_id) REFERENCES campement.participant(id) ON DELETE CASCADE;


--
-- Name: fournisseur fk_fournisseur_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.fournisseur
    ADD CONSTRAINT fk_fournisseur_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: groupe_repas fk_groupe_repas_groupe; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.groupe_repas
    ADD CONSTRAINT fk_groupe_repas_groupe FOREIGN KEY (groupe_id) REFERENCES campement.groupe(id) ON DELETE CASCADE;


--
-- Name: groupe_repas fk_groupe_repas_menu; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.groupe_repas
    ADD CONSTRAINT fk_groupe_repas_menu FOREIGN KEY (menu_id) REFERENCES campement.menu(id) ON DELETE CASCADE;


--
-- Name: groupe fk_groupe_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.groupe
    ADD CONSTRAINT fk_groupe_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: menu_denree fk_menu_denree_conditionnement; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree
    ADD CONSTRAINT fk_menu_denree_conditionnement FOREIGN KEY (conditionnement_id) REFERENCES campement.unite(id) ON DELETE RESTRICT;


--
-- Name: menu_denree fk_menu_denree_denree; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree
    ADD CONSTRAINT fk_menu_denree_denree FOREIGN KEY (denree_id) REFERENCES campement.denree(id) ON DELETE RESTRICT;


--
-- Name: menu_denree fk_menu_denree_menu; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree
    ADD CONSTRAINT fk_menu_denree_menu FOREIGN KEY (menu_id) REFERENCES campement.menu(id) ON DELETE CASCADE;


--
-- Name: menu_denree_quantite fk_menu_denree_quantite_menu_denree; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree_quantite
    ADD CONSTRAINT fk_menu_denree_quantite_menu_denree FOREIGN KEY (menu_denree_id) REFERENCES campement.menu_denree(id) ON DELETE CASCADE;


--
-- Name: menu_denree_quantite fk_menu_denree_quantite_sejour_public_cible; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree_quantite
    ADD CONSTRAINT fk_menu_denree_quantite_sejour_public_cible FOREIGN KEY (sejour_public_cible_id) REFERENCES campement.sejour_public_cible(id) ON DELETE RESTRICT;


--
-- Name: menu_denree fk_menu_denree_recette; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu_denree
    ADD CONSTRAINT fk_menu_denree_recette FOREIGN KEY (recette_id) REFERENCES campement.recette(id) ON DELETE RESTRICT;


--
-- Name: menu fk_menu_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu
    ADD CONSTRAINT fk_menu_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: menu fk_menu_sejour_type_repas; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.menu
    ADD CONSTRAINT fk_menu_sejour_type_repas FOREIGN KEY (sejour_type_repas_id) REFERENCES campement.sejour_type_repas(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock fk_mouvement_stock_annule_par; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT fk_mouvement_stock_annule_par FOREIGN KEY (annule_par_id) REFERENCES campement.utilisateur(id) ON DELETE SET NULL;


--
-- Name: mouvement_stock fk_mouvement_stock_groupe; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT fk_mouvement_stock_groupe FOREIGN KEY (groupe_id) REFERENCES campement.groupe(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock_ligne_conditionnement fk_mouvement_stock_ligne_conditionnement_conditionnement; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne_conditionnement
    ADD CONSTRAINT fk_mouvement_stock_ligne_conditionnement_conditionnement FOREIGN KEY (conditionnement_id) REFERENCES campement.denree_fournisseur_conditionnement(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock_ligne_conditionnement fk_mouvement_stock_ligne_conditionnement_ligne; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne_conditionnement
    ADD CONSTRAINT fk_mouvement_stock_ligne_conditionnement_ligne FOREIGN KEY (mouvement_stock_ligne_id) REFERENCES campement.mouvement_stock_ligne(id) ON DELETE CASCADE;


--
-- Name: mouvement_stock_ligne fk_mouvement_stock_ligne_conditionnement_saisie; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne
    ADD CONSTRAINT fk_mouvement_stock_ligne_conditionnement_saisie FOREIGN KEY (conditionnement_saisie_id) REFERENCES campement.unite(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock_ligne fk_mouvement_stock_ligne_denree; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne
    ADD CONSTRAINT fk_mouvement_stock_ligne_denree FOREIGN KEY (denree_id) REFERENCES campement.denree(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock_ligne fk_mouvement_stock_ligne_mouvement; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne
    ADD CONSTRAINT fk_mouvement_stock_ligne_mouvement FOREIGN KEY (mouvement_stock_id) REFERENCES campement.mouvement_stock(id) ON DELETE CASCADE;


--
-- Name: mouvement_stock_ligne fk_mouvement_stock_ligne_reference_fournisseur; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock_ligne
    ADD CONSTRAINT fk_mouvement_stock_ligne_reference_fournisseur FOREIGN KEY (reference_fournisseur_id) REFERENCES campement.denree_fournisseur(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock fk_mouvement_stock_menu; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT fk_mouvement_stock_menu FOREIGN KEY (menu_id) REFERENCES campement.menu(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock fk_mouvement_stock_origine; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT fk_mouvement_stock_origine FOREIGN KEY (origine_mouvement_id) REFERENCES campement.origine_mouvement(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock fk_mouvement_stock_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT fk_mouvement_stock_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: mouvement_stock fk_mouvement_stock_type; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT fk_mouvement_stock_type FOREIGN KEY (type_mouvement_id) REFERENCES campement.type_mouvement(id) ON DELETE RESTRICT;


--
-- Name: mouvement_stock fk_mouvement_stock_utilisateur; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.mouvement_stock
    ADD CONSTRAINT fk_mouvement_stock_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES campement.utilisateur(id) ON DELETE RESTRICT;


--
-- Name: participant fk_participant_groupe; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.participant
    ADD CONSTRAINT fk_participant_groupe FOREIGN KEY (groupe_id) REFERENCES campement.groupe(id) ON DELETE CASCADE;


--
-- Name: presence_participant fk_presence_participant; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.presence_participant
    ADD CONSTRAINT fk_presence_participant FOREIGN KEY (participant_id) REFERENCES campement.participant(id) ON DELETE CASCADE;


--
-- Name: recette_denree fk_recette_denree_conditionnement; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette_denree
    ADD CONSTRAINT fk_recette_denree_conditionnement FOREIGN KEY (conditionnement_id) REFERENCES campement.unite(id) ON DELETE RESTRICT;


--
-- Name: recette_denree fk_recette_denree_denree; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette_denree
    ADD CONSTRAINT fk_recette_denree_denree FOREIGN KEY (denree_id) REFERENCES campement.denree(id) ON DELETE RESTRICT;


--
-- Name: recette_denree_quantite fk_recette_denree_quantite_ligne; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette_denree_quantite
    ADD CONSTRAINT fk_recette_denree_quantite_ligne FOREIGN KEY (recette_denree_id) REFERENCES campement.recette_denree(id) ON DELETE CASCADE;


--
-- Name: recette_denree_quantite fk_recette_denree_quantite_public; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette_denree_quantite
    ADD CONSTRAINT fk_recette_denree_quantite_public FOREIGN KEY (sejour_public_cible_id) REFERENCES campement.sejour_public_cible(id) ON DELETE RESTRICT;


--
-- Name: recette_denree fk_recette_denree_recette; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette_denree
    ADD CONSTRAINT fk_recette_denree_recette FOREIGN KEY (recette_id) REFERENCES campement.recette(id) ON DELETE CASCADE;


--
-- Name: recette fk_recette_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.recette
    ADD CONSTRAINT fk_recette_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: sejour_public_cible fk_sejour_public_cible_public_cible; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour_public_cible
    ADD CONSTRAINT fk_sejour_public_cible_public_cible FOREIGN KEY (public_cible_id) REFERENCES campement.public_cible(id) ON DELETE RESTRICT;


--
-- Name: sejour_public_cible fk_sejour_public_cible_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour_public_cible
    ADD CONSTRAINT fk_sejour_public_cible_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: sejour_type_repas fk_sejour_type_repas_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour_type_repas
    ADD CONSTRAINT fk_sejour_type_repas_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: sejour_type_repas fk_sejour_type_repas_type_repas; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.sejour_type_repas
    ADD CONSTRAINT fk_sejour_type_repas_type_repas FOREIGN KEY (type_repas_id) REFERENCES campement.type_repas(id) ON DELETE RESTRICT;


--
-- Name: situation_particuliere_participant fk_situation_particuliere_participant_participant; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.situation_particuliere_participant
    ADD CONSTRAINT fk_situation_particuliere_participant_participant FOREIGN KEY (participant_id) REFERENCES campement.participant(id) ON DELETE CASCADE;


--
-- Name: situation_particuliere_participant fk_situation_particuliere_participant_situation; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.situation_particuliere_participant
    ADD CONSTRAINT fk_situation_particuliere_participant_situation FOREIGN KEY (situation_particuliere_id) REFERENCES campement.situation_particuliere(id) ON DELETE CASCADE;


--
-- Name: situation_particuliere fk_situation_particuliere_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.situation_particuliere
    ADD CONSTRAINT fk_situation_particuliere_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: tache_situation_particuliere fk_tache_situation_particuliere_situation; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.tache_situation_particuliere
    ADD CONSTRAINT fk_tache_situation_particuliere_situation FOREIGN KEY (situation_particuliere_id) REFERENCES campement.situation_particuliere(id) ON DELETE CASCADE;


--
-- Name: utilisateur fk_utilisateur_dernier_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.utilisateur
    ADD CONSTRAINT fk_utilisateur_dernier_sejour FOREIGN KEY (dernier_sejour_id) REFERENCES campement.sejour(id) ON DELETE SET NULL;


--
-- Name: utilisateur fk_utilisateur_groupe; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.utilisateur
    ADD CONSTRAINT fk_utilisateur_groupe FOREIGN KEY (groupe_id) REFERENCES campement.groupe(id) ON DELETE RESTRICT;


--
-- Name: utilisateur_sejour fk_utilisateur_sejour_sejour; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.utilisateur_sejour
    ADD CONSTRAINT fk_utilisateur_sejour_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE;


--
-- Name: utilisateur_sejour fk_utilisateur_sejour_utilisateur; Type: FK CONSTRAINT; Schema: campement; Owner: -
--

ALTER TABLE ONLY campement.utilisateur_sejour
    ADD CONSTRAINT fk_utilisateur_sejour_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES campement.utilisateur(id) ON DELETE CASCADE;


--
--

--
--



SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: origine_mouvement; Type: TABLE DATA; Schema: campement; Owner: -
--

INSERT INTO campement.origine_mouvement (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-4375-7946-8618-9546748d13c0', 'DONATION', 'Donation', 7, true, '2026-09-02 19:58:07.727892+00', '2026-09-02 19:58:07.727892+00');


--
-- Data for Name: public_cible; Type: TABLE DATA; Schema: campement; Owner: -
--

INSERT INTO campement.public_cible (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-40a6-78a6-ac50-4e933a939df3', 'FARFADETS', 'Farfadets', 1, true, '2026-09-02 19:58:07.00858+00', '2026-09-02 19:58:07.00858+00');
INSERT INTO campement.public_cible (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f31-78f8-8272-2746439b7f19', 'LOUVETEAUX_JEANNETTES', 'Louveteaux-Jeannettes', 2, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');
INSERT INTO campement.public_cible (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f32-726c-b988-c698fbbfe798', 'SCOUTS_GUIDES', 'Scouts-Guides', 3, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');
INSERT INTO campement.public_cible (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f32-73cd-9f88-457daf9bd628', 'PIONNIERS_CARAVELLES', 'Pionniers-Caravelles', 4, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');
INSERT INTO campement.public_cible (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-40a6-7d4b-b812-3608c8f8d44d', 'COMPAGNONS', 'Compagnons', 5, true, '2026-09-02 19:58:07.00858+00', '2026-09-02 19:58:07.00858+00');
INSERT INTO campement.public_cible (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f32-7496-9743-f9c626c64a2e', 'ADULTE', 'Adulte', 6, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');


--
-- Data for Name: type_mouvement; Type: TABLE DATA; Schema: campement; Owner: -
--

INSERT INTO campement.type_mouvement (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f36-70ee-a850-681f694051a9', 'ENTREE', 'Entrée', 1, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');
INSERT INTO campement.type_mouvement (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f37-7235-8b57-6dd535584b20', 'SORTIE', 'Sortie', 2, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');


--
-- Data for Name: type_repas; Type: TABLE DATA; Schema: campement; Owner: -
--

INSERT INTO campement.type_repas (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f2d-7c05-932b-7811d5bf7123', 'PETIT_DEJEUNER', 'Petit-déjeuner', 1, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');
INSERT INTO campement.type_repas (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f2e-7c15-bde2-70b89ddb4351', 'DEJEUNER', 'Déjeuner', 2, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');
INSERT INTO campement.type_repas (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f2e-7e74-a981-619a1bddbf6b', 'GOUTER', 'Goûter', 3, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');
INSERT INTO campement.type_repas (id, code, libelle, ordre, actif, created_at, updated_at) VALUES ('01a063b3-3f2e-7efa-be4b-d5026e41b874', 'DINER', 'Dîner', 4, true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00');


--
-- Data for Name: utilisateur; Type: TABLE DATA; Schema: campement; Owner: -
--

INSERT INTO campement.utilisateur (id, groupe_id, email, mot_de_passe, prenom, nom, roles, actif, created_at, updated_at, changement_mot_de_passe_requis, jeton_reinitialisation, expiration_jeton_reinitialisation, dernier_sejour_id, desactive_at) VALUES ('01a063b3-3f39-703e-a253-61dd0a738aeb', NULL, 'saisie-consommation@campement.local', '!', 'Saisie', 'Consommation', '["ROLE_TECHNIQUE"]', true, '2026-09-02 19:58:06+00', '2026-09-02 19:58:06+00', false, NULL, NULL, NULL, NULL);

--
-- Données de référence des unités
--

--
--




--
-- Data for Name: unite; Type: TABLE DATA; Schema: campement; Owner: -
--

INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-3f2b-74fb-9467-55928ea0eeb9', 'gramme', 'g', true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-3f2c-70f7-aff1-3274ed80e7fc', 'kilogramme', 'kg', true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-3f2c-72c3-96d4-2fa1b3f5fb90', 'litre', 'L', true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-3f2c-7399-ae10-1ef690718b2c', 'millilitre', 'mL', true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-3f2c-745f-950f-ef90a089bf45', 'pièce', 'pc', true, '2026-09-02 19:58:06.145262+00', '2026-09-02 19:58:06.145262+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4002-7f4d-8b20-4676cf53f063', 'palette', 'palette', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-75f4-83f3-8bb0cdf8d84e', 'étage', 'étage', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-76e6-aaef-3113a77285b9', 'carton', 'carton', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-77b1-9b4a-e22fc625c358', 'conserve', 'conserve', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-787c-922e-c4bb97642cf9', 'boîte', 'boîte', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-7941-81ba-d0d6d561984c', 'sachet', 'sachet', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-7a07-9333-3264b86d6b0e', 'bouteille', 'bouteille', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-7b28-b574-183e5525e7ee', 'brique', 'brique', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-7c07-957f-0d194edaefeb', 'pot', 'pot', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-7d5a-90f3-6ce0aeba9d30', 'barquette', 'barquette', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);
INSERT INTO campement.unite (id, nom, symbole, actif, created_at, updated_at, utilisable_conditionnement) VALUES ('01a063b3-4003-7e3a-95ae-3e69b336d8a2', 'paquet', 'paquet', true, '2026-09-02 19:58:06.789876+00', '2026-09-02 19:58:06.789876+00', true);


--
--


--
--
