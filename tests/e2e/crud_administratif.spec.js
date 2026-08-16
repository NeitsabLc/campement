import { expect, test } from '@playwright/test';
import { comptes, seConnecter, selectionnerSejour, suffixeUnique } from './helpers.js';

const sejourPrincipal = 'Séjour de développement';

test.beforeEach(async ({ page }) => {
  await seConnecter(page, comptes.gestionnaire);
  await selectionnerSejour(page, sejourPrincipal);
});

test('une situation particulière peut être créée, consultée, modifiée puis supprimée', async ({ page }) => {
  const suffixe = suffixeUnique();
  const nom = `Situation E2E ${suffixe}`;
  const nomModifie = `${nom} modifiée`;

  await page.goto('/situations-particulieres/nouvelle');
  await page.locator('input[name="libelle"]').fill(nom);
  await page.locator('input[name="date_situation"]').fill('2026-07-10');
  await page.getByRole('button', { name: 'Créer la situation' }).click();
  await expect(page).toHaveURL(/\/situations-particulieres\/[0-9a-f-]+\/modifier$/);
  await expect(page.getByRole('status')).toContainText('La situation particulière a été créée.');

  await page.locator('input[name="libelle"]').fill(nomModifie);
  await page.locator('input[name="date_situation"]').fill('2026-07-11');
  await page.locator('input[name="informations[]"]').first().check();
  await page.getByRole('button', { name: 'Enregistrer les modifications' }).click();
  await expect(page.getByRole('status')).toContainText('La situation particulière a été mise à jour.');
  await expect(page.getByRole('heading', { name: nomModifie })).toBeVisible();

  await page.goto('/situations-particulieres');
  await expect(page.getByRole('link', { name: nomModifie, exact: true })).toBeVisible();
  await page.getByRole('link', { name: `Supprimer ${nomModifie}` }).click();
  await expect(page.getByRole('heading', { name: 'Supprimer cette situation ?' })).toBeVisible();
  await page.getByRole('button', { name: 'Supprimer définitivement' }).click();
  await expect(page).toHaveURL(/\/situations-particulieres$/);
  await expect(page.getByRole('status')).toContainText('La situation particulière a été supprimée.');
  await expect(page.getByText(nomModifie, { exact: true })).toHaveCount(0);
});

test('une présence peut être créée, modifiée puis supprimée en repassant la personne présente', async ({ page }) => {
  const participant = 'Camille Fixture';
  const date = '2026-07-10';
  await page.goto(`/administratif/registre-presence?date=${date}`);
  const modifier = () => page.locator(`a.presence-daily-row[aria-label="Modifier la présence de ${participant}"]`);

  await modifier().click();
  await page.locator('input[name="statut"][value="absent"]').check();
  await page.locator('textarea[name="commentaire"]').fill('Absence E2E');
  await page.getByRole('button', { name: 'Enregistrer la présence' }).click();
  await expect(page.getByRole('status')).toContainText('Le registre de présence a été mis à jour.');
  let ligne = page.locator('.presence-daily-row').filter({ hasText: participant });
  await expect(ligne).toContainText('Absent ce jour');
  await expect(ligne).toContainText('Absence E2E');

  await modifier().click();
  await page.locator('input[name="statut"][value="depart"]').check();
  await page.locator('textarea[name="commentaire"]').fill('Départ E2E');
  await page.getByRole('button', { name: 'Enregistrer la présence' }).click();
  ligne = page.locator('.presence-daily-row').filter({ hasText: participant });
  await expect(ligne).toContainText('A quitté le séjour');

  await modifier().click();
  await page.locator('input[name="statut"][value="present"]').check();
  await page.locator('textarea[name="commentaire"]').fill('');
  await page.getByRole('button', { name: 'Enregistrer la présence' }).click();
  ligne = page.locator('.presence-daily-row').filter({ hasText: participant });
  await expect(ligne).toContainText('Présent');
  await expect(ligne).not.toContainText('Départ E2E');
});
