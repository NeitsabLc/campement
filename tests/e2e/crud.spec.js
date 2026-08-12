import { expect, test } from '@playwright/test';
import { comptes, seConnecter, selectionnerSejour, suffixeUnique } from './helpers.js';

const sejourPrincipal = 'Séjour de développement';

test.beforeEach(async ({ page }) => {
  await seConnecter(page, comptes.gestionnaire);
  await selectionnerSejour(page, sejourPrincipal);
});

test('les principales pages de création CRUD sont accessibles au gestionnaire', async ({ page }) => {
  const pagesCreation = [
    ['/groupes/ajouter', 'Ajouter une unité participante'],
    ['/fournisseurs/ajouter', 'Ajouter un fournisseur'],
    ['/denrees/ajouter', 'Ajouter une denrée'],
    ['/recettes/ajouter', 'Ajouter une recette'],
    ['/stocks/mouvement', 'Ajouter un mouvement de stock'],
    ['/administratif/participants/ajouter?type=jeune', 'Ajouter un jeune'],
    ['/utilisateurs/ajouter', 'Ajouter un utilisateur'],
  ];

  for (const [chemin, titre] of pagesCreation) {
    await test.step(chemin, async () => {
      const reponse = await page.goto(chemin);
      expect(reponse?.status()).toBe(200);
      await expect(page).toHaveURL(new RegExp(chemin.replace(/[?]/g, '\\?')));
      await expect(page.getByRole('heading', { name: titre, exact: true })).toBeVisible();
    });
  }
});

test('une unité participante peut être créée, consultée, modifiée puis désactivée', async ({ page }) => {
  const suffixe = suffixeUnique();
  const nomInitial = `Unité E2E ${suffixe}`;
  const nomModifie = `${nomInitial} modifiée`;

  await page.goto('/groupes/ajouter');
  await page.getByLabel('Nom de l’unité').fill(nomInitial);
  await page.locator('input[name="type"]').first().check();
  await page.getByLabel('Effectif jeune').fill('12');
  await page.getByLabel('Effectif adulte').fill('3');
  await page.getByRole('button', { name: 'Enregistrer l’unité' }).click();

  await expect(page).toHaveURL(/\/groupes$/);
  await expect(page.getByRole('status')).toContainText(`L’unité « ${nomInitial} » a bien été créée.`);
  const carteCreee = page.locator('article.group-row').filter({ hasText: nomInitial });
  await expect(carteCreee).toContainText('15 personnes prévues');

  await page.locator(`a.edit-group-button[aria-label="Modifier l’unité ${nomInitial}"]`).click();
  await expect(page.getByRole('heading', { name: nomInitial, exact: true })).toBeVisible();
  await page.getByLabel('Nom de l’unité').fill(nomModifie);
  await page.getByLabel('Effectif jeune').fill('14');
  await page.getByRole('button', { name: 'Enregistrer l’unité' }).click();

  await expect(page).toHaveURL(/\/groupes$/);
  await expect(page.getByRole('status')).toContainText(`L’unité « ${nomModifie} » a bien été modifiée.`);
  await expect(page.locator('article.group-row').filter({ hasText: nomModifie })).toContainText('17 personnes prévues');

  await page.getByRole('button', { name: `Désactiver l’unité ${nomModifie}` }).click();
  await expect(page.getByRole('dialog', { name: 'Désactiver cette unité ?' })).toBeVisible();
  await page.getByRole('dialog', { name: 'Désactiver cette unité ?' })
    .getByRole('button', { name: 'Désactiver l’unité' }).click();

  await expect(page).toHaveURL(/\/groupes$/);
  await expect(page.getByRole('status')).toContainText(`L’unité « ${nomModifie} » a bien été désactivée.`);
  await expect(page.getByText(nomModifie, { exact: true })).toHaveCount(0);

  await page.getByLabel('Afficher les unités inactives').check();
  await expect(page).toHaveURL(/\/groupes\?inactifs=1$/);
  await expect(page.locator('article.group-row--inactive').filter({ hasText: nomModifie })).toContainText('Inactif');
});
