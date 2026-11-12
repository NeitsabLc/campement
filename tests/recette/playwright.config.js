import { defineConfig, devices } from '@playwright/test';

// Suite lancée à la main par `verificateur` (mode recette) avant une promotion,
// jamais en CI ni par `npm run test:e2e` / `npm run test:browser`.
const url = process.env.RECETTE_URL;
const manquantes = ['RECETTE_URL', 'RECETTE_EMAIL', 'RECETTE_MOT_DE_PASSE'].filter((nom) => !process.env[nom]);

if (process.env.CI) {
  throw new Error('Refus de démarrer : cette suite ne se lance jamais en CI.');
}

if (manquantes.length > 0) {
  throw new Error(`Variables d’environnement manquantes : ${manquantes.join(', ')}. Voir docs/context/infra.md.`);
}

let hote;
try {
  const adresse = new URL(url);
  if (adresse.protocol !== 'https:') {
    throw new Error('protocole');
  }
  hote = adresse.hostname;
} catch {
  throw new Error('RECETTE_URL doit être une URL HTTPS valide.');
}

if (!hote.includes('recette')) {
  throw new Error('Refus de démarrer : le nom d’hôte de RECETTE_URL doit contenir « recette » (protection de la production).');
}

// En cas d'échec, Playwright écrit `error-context.md` sous test-results/ (ignoré par Git).
// Cette variable supprime l'instantané ARIA de toute la page. Aucune option ne supprime le
// fichier : il garde le message d'erreur et l'extrait ARIA du seul élément testé.
process.env.PLAYWRIGHT_NO_COPY_PROMPT = '1';

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: 'list',
  outputDir: '../../test-results/recette',
  use: {
    baseURL: url,
    // Pas de trace ni de vidéo : elles enregistreraient le mot de passe saisi.
    // Pas de capture : les pages affichent des données du séjour.
    screenshot: 'off',
    trace: 'off',
    video: 'off',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
