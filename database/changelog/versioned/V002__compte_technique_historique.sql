--liquibase formatted sql

--changeset campement:V002-compte-technique-historique splitStatements:true endDelimiter:;
--comment: Ajoute le compte technique qui reprend les mouvements de stock lors de la suppression d'un utilisateur

INSERT INTO campement.utilisateur (
    id,
    groupe_id,
    email,
    mot_de_passe,
    prenom,
    nom,
    roles,
    actif,
    changement_mot_de_passe_requis
)
VALUES (
    '019d2540-9660-7d7d-8e52-cf53e53b1c18',
    NULL,
    'historique@campement.local',
    '!',
    'Compte',
    'historique',
    '["ROLE_TECHNIQUE"]'::jsonb,
    TRUE,
    FALSE
)
ON CONFLICT (email) DO UPDATE
SET groupe_id = NULL,
    mot_de_passe = EXCLUDED.mot_de_passe,
    prenom = EXCLUDED.prenom,
    nom = EXCLUDED.nom,
    roles = EXCLUDED.roles,
    actif = TRUE,
    changement_mot_de_passe_requis = FALSE,
    desactive_at = NULL,
    updated_at = CURRENT_TIMESTAMP(0);
