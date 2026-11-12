import { expect, test } from '@playwright/test';
import { seConnecter, seDeconnecter, selectionnerSejour } from '../e2e/helpers.js';

const email = process.env.RECETTE_EMAIL;
const motDePasse = process.env.RECETTE_MOT_DE_PASSE;
const sejourControle = 'Contrôle recette automatique';
const repere = 'Contrôle recette automatique';

test.describe.configure({ mode: 'serial' });

// Surveille les réponses serveur en erreur pendant un test.
function surveillerErreursServeur(page) {
  const erreurs = [];
  page.on('response', (reponse) => {
    if (reponse.status() >= 500) {
      erreurs.push(`${reponse.status()} ${new URL(reponse.url()).pathname}`);
    }
  });
  return erreurs;
}

async function verifierPage(page, chemin, titre) {
  const reponse = await page.goto(chemin);
  expect(reponse.status(), `${chemin} répond`).toBeLessThan(500);
  await expect(page.getByRole('heading', { level: 1 })).toContainText(titre);
  await expect(page.locator('body')).not.toContainText(/Exception|Stack trace/);
}

// Refuse de continuer si le séjour courant n'est pas exactement le séjour de contrôle.
async function verifierSejourControle(page) {
  await page.goto('/sejours');
  const boutonsSelectionnes = page.getByRole('button', { name: 'Sélectionné', exact: true });
  await expect(boutonsSelectionnes, 'un seul séjour doit être sélectionné').toHaveCount(1);
  const carte = page.locator('article.management-card').filter({ has: boutonsSelectionnes });
  await expect(
    carte.getByRole('heading', { name: sejourControle, exact: true }),
    `le séjour sélectionné doit être « ${sejourControle} »`,
  ).toBeVisible();
}

async function preparerSejourControle(page) {
  await selectionnerSejour(page, sejourControle);
  await verifierSejourControle(page);
}

// Annule tout mouvement encore actif du séjour de contrôle (il est dédié : aucun mouvement réel).
async function annulerMouvementsActifs(page) {
  await verifierSejourControle(page);
  await page.goto('/stocks');
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Mouvements de stock');
  const liens = await page.locator('a.button-link[href$="/annuler"]').evaluateAll((noeuds) => noeuds.map((n) => n.getAttribute('href')));
  for (const lien of liens) {
    await page.goto(lien);
    await page.getByLabel('Motif').fill(`${repere} : nettoyage`);
    await page.getByRole('button', { name: 'Confirmer l’annulation' }).click();
    await expect(page.getByRole('status')).toContainText('Le mouvement a été annulé');
  }
}

test('lecture : parcours des pages du gestionnaire', async ({ page }) => {
  const erreurs = surveillerErreursServeur(page);
  await seConnecter(page, email, motDePasse);
  await preparerSejourControle(page);

  const pages = [
    ['/', 'Bonjour'],
    ['/sejours', 'Mes séjours'],
    ['/stocks', 'Mouvements de stock'],
    ['/stocks/mouvement', 'Ajouter un mouvement de stock'],
    ['/denrees', 'Denrées'],
    ['/fournisseurs', 'Fournisseurs'],
    ['/menus', 'Menus'],
    ['/recettes', 'Recettes'],
    ['/groupes', 'Unités participantes'],
    ['/utilisateurs', 'Utilisateurs'],
    ['/administratif/participants', 'Participants'],
    ['/intendance/commande', 'Commande'],
    ['/intendance/distribution', 'Distribution'],
  ];
  for (const [chemin, titre] of pages) {
    await verifierPage(page, chemin, titre);
  }

  // La création de séjour est réservée à l'administrateur : refus propre (403), sans erreur serveur.
  const creationSejour = await page.goto('/sejours/ajouter');
  expect(creationSejour.status()).toBe(403);
  await expect(page.locator('body')).not.toContainText(/Exception|Stack trace/);

  await page.goto('/');
  await seDeconnecter(page);
  expect(erreurs).toEqual([]);
});

test.describe('écriture : mouvement de stock', () => {
  test.afterEach(async ({ page }) => {
    // Nettoyage garanti, même si le test a échoué (nouvelle session si besoin).
    await page.goto('/login');
    if (new URL(page.url()).pathname === '/login') {
      await seConnecter(page, email, motDePasse);
    }
    await preparerSejourControle(page);
    await annulerMouvementsActifs(page);
    await page.goto('/stocks');
    await expect(page.locator('a.button-link[href$="/annuler"]')).toHaveCount(0);
  });

  test('création, modification et annulation', async ({ page }) => {
    const erreurs = surveillerErreursServeur(page);
    await seConnecter(page, email, motDePasse);
    await preparerSejourControle(page);
    await annulerMouvementsActifs(page);

    // Création : une entrée « Correction manuelle » (ni fournisseur ni stock préalable requis).
    await verifierSejourControle(page);
    await page.goto('/stocks/mouvement');
    await page.locator('.movement-type-switch label').filter({ hasText: 'Entrée' }).click();
    await page.getByLabel(/Origine du mouvement/).selectOption({ label: 'Correction manuelle' });
    const champDenree = page.locator('.movement-line-food').first();
    await champDenree.locator('.searchable-select__trigger').click();
    await expect(champDenree.locator('.searchable-select__option'), 'aucune denrée active dans le séjour de contrôle').not.toHaveCount(0);
    await champDenree.locator('.searchable-select__option').first().click();
    const conditionnement = page.locator('select[data-line-exit-unit]').first();
    await expect(conditionnement.locator('option')).not.toHaveCount(1);
    if ('' === (await conditionnement.inputValue())) {
      await conditionnement.selectOption({ index: 1 });
    }
    await page.locator('input[data-line-quantity]').first().fill('1');
    await page.getByRole('button', { name: 'Enregistrer le mouvement' }).click();

    await expect(page.getByRole('status')).toContainText('Mouvement de stock enregistré');
    await expect(page.locator('a.button-link[href$="/annuler"]')).toHaveCount(1);

    // Modification : le motif porte le repère.
    await verifierSejourControle(page);
    await page.goto('/stocks');
    await page.locator('a.stock-edit-button').click();
    await expect(page.getByRole('button', { name: 'Enregistrer les modifications' })).toBeVisible();
    await page.locator('input[data-line-quantity]').first().fill('2');
    await page.locator('textarea[name="motif_audit"]').fill(`${repere} : modification`);
    await page.getByRole('button', { name: 'Enregistrer les modifications' }).click();
    await expect(page.getByRole('status')).toContainText('Mouvement de stock modifié');

    // Annulation : le mouvement reste dans le journal, marqué « Annulé ».
    await verifierSejourControle(page);
    await page.goto('/stocks');
    await page.locator('a.button-link[href$="/annuler"]').click();
    await page.getByLabel('Motif').fill(`${repere} : annulation`);
    await page.getByRole('button', { name: 'Confirmer l’annulation' }).click();
    await expect(page.getByRole('status')).toContainText('Le mouvement a été annulé');
    await expect(page.locator('a.button-link[href$="/annuler"]')).toHaveCount(0);
    await expect(page.locator('.stock-list-row').filter({ hasText: 'Annulé' })).not.toHaveCount(0);
    expect(erreurs).toEqual([]);
  });
});
