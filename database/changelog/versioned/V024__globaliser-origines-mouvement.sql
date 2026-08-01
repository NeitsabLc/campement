--liquibase formatted sql

--changeset campement:V024-globaliser-origines-mouvement splitStatements:true endDelimiter:;
--comment: Rend les origines de mouvement communes à tous les séjours

WITH origines_canoniques AS (
    SELECT code, (array_agg(id ORDER BY created_at, id))[1] AS id
    FROM campement.origine_mouvement
    GROUP BY code
)
UPDATE campement.mouvement_stock AS mouvement
SET origine_mouvement_id = canonique.id
FROM campement.origine_mouvement AS origine,
     origines_canoniques AS canonique
WHERE mouvement.origine_mouvement_id = origine.id
  AND canonique.code = origine.code
  AND mouvement.origine_mouvement_id <> canonique.id;

WITH origines_canoniques AS (
    SELECT code, (array_agg(id ORDER BY created_at, id))[1] AS id
    FROM campement.origine_mouvement
    GROUP BY code
)
DELETE FROM campement.origine_mouvement AS origine
USING origines_canoniques AS canonique
WHERE origine.code = canonique.code
  AND origine.id <> canonique.id;

ALTER TABLE campement.origine_mouvement
    DROP CONSTRAINT fk_origine_mouvement_sejour,
    DROP CONSTRAINT uq_origine_mouvement_code,
    DROP COLUMN sejour_id;

ALTER TABLE campement.origine_mouvement
    ADD CONSTRAINT uq_origine_mouvement_code UNIQUE (code);

--rollback ALTER TABLE campement.origine_mouvement ADD COLUMN sejour_id UUID; ALTER TABLE campement.origine_mouvement DROP CONSTRAINT uq_origine_mouvement_code; ALTER TABLE campement.origine_mouvement ADD CONSTRAINT uq_origine_mouvement_code UNIQUE (sejour_id, code); ALTER TABLE campement.origine_mouvement ADD CONSTRAINT fk_origine_mouvement_sejour FOREIGN KEY (sejour_id) REFERENCES campement.sejour(id) ON DELETE CASCADE; CREATE INDEX idx_origine_mouvement_sejour ON campement.origine_mouvement(sejour_id);
