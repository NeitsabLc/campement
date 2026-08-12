--liquibase formatted sql

--changeset campement:D005-jeu-donnees-parcours-navigateur context:dev splitStatements:true endDelimiter:;
--comment: Jeu de données cohérent pour les parcours de développement et les tests navigateur

UPDATE campement.sejour
SET module_intendance_actif = TRUE,
    module_administratif_actif = TRUE,
    module_situations_particulieres_actif = TRUE,
    actif = TRUE
WHERE nom = 'Séjour de développement'
  AND date_debut = DATE '2026-07-01'
  AND date_fin = DATE '2026-07-31';

INSERT INTO campement.participant (
    groupe_id,
    type,
    nom,
    prenom,
    date_naissance,
    telephone_parent_1,
    email_parents,
    date_debut_presence,
    date_fin_presence
)
SELECT groupe.id,
       'jeune',
       'Fixture',
       'Camille',
       DATE '2013-05-12',
       '0600000000',
       'responsables-fixture@example.test',
       DATE '2026-07-01',
       DATE '2026-07-31'
FROM campement.groupe AS groupe
JOIN campement.sejour AS sejour ON sejour.id = groupe.sejour_id
WHERE groupe.nom = 'Groupe de développement'
  AND sejour.nom = 'Séjour de développement'
  AND sejour.date_debut = DATE '2026-07-01'
  AND sejour.date_fin = DATE '2026-07-31'
  AND NOT EXISTS (
      SELECT 1
      FROM campement.participant AS participant
      WHERE participant.groupe_id = groupe.id
        AND participant.nom = 'Fixture'
        AND participant.prenom = 'Camille'
        AND participant.email_parents = 'responsables-fixture@example.test'
  );

--rollback DELETE FROM campement.participant WHERE nom = 'Fixture' AND prenom = 'Camille' AND email_parents = 'responsables-fixture@example.test'; UPDATE campement.sejour SET module_situations_particulieres_actif = FALSE WHERE nom = 'Séjour de développement' AND date_debut = DATE '2026-07-01' AND date_fin = DATE '2026-07-31';
