import { execFileSync } from 'node:child_process';

function echapperSql(valeur) {
  return valeur.replaceAll("'", "''");
}

export default function nettoyerDonneesE2E() {
  const execution = process.env.CAMPEMENT_E2E_RUN_ID;
  if (!execution) {
    throw new Error('Identifiant d’exécution E2E absent : nettoyage impossible.');
  }

  const prefixeUnite = echapperSql(`Unité E2E ${execution}-%`);
  const prefixeSejour = echapperSql(`Séjour E2E isolé ${execution}-%`);
  const prefixeFournisseur = echapperSql(`Fournisseur E2E ${execution}-%`);
  const prefixeDenree = echapperSql(`Denrée E2E ${execution}-%`);
  const prefixeRecette = echapperSql(`Recette E2E ${execution}-%`);
  const prefixeSituation = echapperSql(`Situation E2E ${execution}-%`);
  const debutExecution = Number.parseInt(execution.split('-')[0], 10) / 1000;
  const sql = [
    'BEGIN',
    `DELETE FROM campement.menu WHERE created_at >= to_timestamp(${debutExecution}) AND special_code = 'EXPLO' AND sejour_id IN (SELECT id FROM campement.sejour WHERE nom = 'Séjour de développement')`,
    `DELETE FROM campement.presence_participant WHERE date_presence = DATE '2026-07-10' AND participant_id IN (SELECT id FROM campement.participant WHERE prenom = 'Camille' AND nom = 'Fixture')`,
    `DELETE FROM campement.situation_particuliere WHERE libelle LIKE '${prefixeSituation}'`,
    `DELETE FROM campement.recette WHERE nom LIKE '${prefixeRecette}'`,
    `DELETE FROM campement.denree_fournisseur WHERE denree_id IN (SELECT id FROM campement.denree WHERE nom LIKE '${prefixeDenree}') OR fournisseur_id IN (SELECT id FROM campement.fournisseur WHERE nom LIKE '${prefixeFournisseur}')`,
    `DELETE FROM campement.denree WHERE nom LIKE '${prefixeDenree}'`,
    `DELETE FROM campement.fournisseur WHERE nom LIKE '${prefixeFournisseur}'`,
    `DELETE FROM campement.groupe WHERE nom LIKE '${prefixeUnite}'`,
    `DELETE FROM campement.sejour WHERE nom LIKE '${prefixeSejour}'`,
    'COMMIT',
  ].join('; ');

  execFileSync(
    'docker',
    [
      'compose',
      'exec',
      '--no-TTY',
      'database',
      'sh',
      '-c',
      'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --set=ON_ERROR_STOP=1 --command="$1"',
      'sh',
      sql,
    ],
    { stdio: 'inherit' },
  );
}
