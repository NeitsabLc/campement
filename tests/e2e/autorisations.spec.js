import { expect, test } from '@playwright/test';
import {
  comptes,
  seConnecter,
  seDeconnecter,
  selectionnerSejour,
  suffixeUnique,
} from './helpers.js';

const sejourPrincipal = 'Séjour de développement';

test('les pages d’administration et de gestion respectent les rôles', async ({ page }) => {
  await seConnecter(page, comptes.groupe);

  for (const chemin of ['/sejours', '/utilisateurs']) {
    await test.step(`ROLE_GROUPE interdit sur ${chemin}`, async () => {
      const reponse = await page.goto(chemin);
      expect(reponse?.status()).toBe(403);
    });
  }

  for (const chemin of ['/groupes', '/stocks']) {
    await test.step(`ROLE_GROUPE ne peut pas consulter ${chemin}`, async () => {
      const reponse = await page.goto(chemin);
      expect(reponse?.status()).toBe(403);
    });
  }

  for (const chemin of ['/menus', '/administratif/participants']) {
    await test.step(`ROLE_GROUPE peut consulter ${chemin}`, async () => {
      const reponse = await page.goto(chemin);
      expect(reponse?.status()).toBe(200);
      await expect(page).toHaveURL(new RegExp(`${chemin}$`));
    });
  }

  await seDeconnecter(page);
  await seConnecter(page, comptes.gestionnaire);

  const creationSejour = await page.goto('/sejours/ajouter');
  expect(creationSejour?.status()).toBe(403);

  await page.goto('/utilisateurs/ajouter');
  expect(await page.locator('option[value="ROLE_ADMIN"]').count()).toBe(0);

  await seDeconnecter(page);
  await seConnecter(page, comptes.administrateur);

  const creationSejourAdmin = await page.goto('/sejours/ajouter');
  expect(creationSejourAdmin?.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Ajouter un séjour' })).toBeVisible();
});

test('les sessions et les autorisations restent isolées entre les rôles', async ({ browser }) => {
  const options = { baseURL: process.env.APP_BASE_URL ?? 'http://127.0.0.1:8080' };
  const contexteAdministrateur = await browser.newContext(options);
  const contexteGroupe = await browser.newContext(options);
  const pageAdministrateur = await contexteAdministrateur.newPage();
  const pageGroupe = await contexteGroupe.newPage();

  try {
    await seConnecter(pageAdministrateur, comptes.administrateur);
    await seConnecter(pageGroupe, comptes.groupe);

    await expect(pageAdministrateur.locator('.user-summary')).toContainText('ROLE_ADMIN');
    await expect(pageGroupe.locator('.user-summary')).toContainText('ROLE_GROUPE');

    expect((await pageAdministrateur.goto('/sejours/ajouter'))?.status()).toBe(200);
    expect((await pageGroupe.goto('/sejours/ajouter'))?.status()).toBe(403);

    await pageAdministrateur.goto('/sejours');
    await pageGroupe.goto('/menus');
    await expect(pageAdministrateur.locator('.user-summary')).toContainText('ROLE_ADMIN');
    await expect(pageGroupe.locator('.user-summary')).toContainText('ROLE_GROUPE');
  } finally {
    await contexteAdministrateur.close();
    await contexteGroupe.close();
  }
});

test('le séjour actif et les affectations isolent les données entre séjours', async ({ page }) => {
  const nomSecondaire = `Séjour E2E isolé ${suffixeUnique()}`;

  await seConnecter(page, comptes.administrateur);
  await selectionnerSejour(page, sejourPrincipal);
  await page.goto('/groupes');
  const lienGroupePrincipal = await page.locator('a.edit-group-button').first().getAttribute('href');
  expect(lienGroupePrincipal).toMatch(/^\/groupes\/[0-9a-f-]+\/modifier$/);

  await page.goto('/sejours/ajouter');
  await page.getByLabel('Nom').fill(nomSecondaire);
  await page.getByLabel('Date de début').fill('2027-07-01');
  await page.getByLabel('Date de fin').fill('2027-07-15');
  await page.locator('input[name="publics[]"]').first().check();
  await page.getByRole('button', { name: 'Enregistrer le séjour' }).click();

  await expect(page).toHaveURL(/\/sejours$/);
  await expect(page.getByRole('status')).toContainText('Le séjour a été créé.');
  const carteSecondaire = page.locator('article.management-card').filter({
    has: page.getByRole('heading', { name: nomSecondaire, exact: true }),
  });
  await expect(carteSecondaire).toHaveCount(1);
  const lienSecondaire = await carteSecondaire.getByRole('link', { name: nomSecondaire, exact: true }).getAttribute('href');
  expect(lienSecondaire).toMatch(/^\/sejours\/[0-9a-f-]+\/modifier$/);

  await carteSecondaire.getByRole('button', { name: 'Sélectionner' }).click();
  await expect(carteSecondaire.getByRole('button', { name: 'Sélectionné' })).toBeVisible();

  const ressourceAutreSejour = await page.goto(lienGroupePrincipal);
  expect(ressourceAutreSejour?.status()).toBe(404);

  await page.context().clearCookies();
  await seConnecter(page, comptes.gestionnaire);
  await page.goto('/sejours');
  await expect(page.getByRole('heading', { name: 'Mes séjours' })).toBeVisible();
  await expect(page.getByText(nomSecondaire, { exact: true })).toHaveCount(0);

  const modificationHorsPerimetre = await page.goto(lienSecondaire);
  expect(modificationHorsPerimetre?.status()).toBe(404);

  await page.context().clearCookies();
  await seConnecter(page, comptes.administrateur);
  await page.goto('/sejours');
  const carteACleaner = page.locator('article.management-card').filter({
    has: page.getByRole('heading', { name: nomSecondaire, exact: true }),
  });
  await carteACleaner.getByRole('button', { name: `Désactiver ${nomSecondaire}` }).click();
  await page.getByRole('dialog', { name: 'Désactiver ce séjour ?' })
    .getByRole('button', { name: 'Désactiver le séjour' }).click();
  await expect(page.getByRole('status')).toContainText('Le séjour a été désactivé.');
});
