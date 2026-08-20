import { expect } from '@playwright/test';

export const motDePasse = 'Campement?2026!';

export const comptes = {
  administrateur: 'admin@campement.local',
  gestionnaire: 'gestionnaire@campement.local',
  groupe: 'groupe@campement.local',
};

export async function seConnecter(page, email) {
  await page.goto('/login');
  await page.getByLabel('Adresse e-mail').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(motDePasse);
  await page.getByRole('button', { name: 'Se connecter' }).click();

  await expect(page).not.toHaveURL(/\/login$/);
  await expect(page.locator('.user-summary')).toBeVisible();
}

export async function seDeconnecter(page) {
  await page.getByRole('button', { name: 'Se déconnecter' }).click();

  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Ravi de vous revoir' })).toBeVisible();
}

export async function selectionnerSejour(page, nom) {
  await page.goto('/sejours');

  const carte = page.locator('article.management-card').filter({
    has: page.getByRole('heading', { name: nom, exact: true }),
  }).filter({ hasText: 'Actif' });
  await expect(carte).toHaveCount(1);
  await carte.getByRole('button', { name: /Sélectionner|Sélectionné/ }).click();

  await expect(page).toHaveURL(/\/sejours$/);
  await expect(carte.getByRole('button', { name: 'Sélectionné' })).toBeVisible();
}

export function suffixeUnique() {
  const execution = process.env.CAMPEMENT_E2E_RUN_ID ?? Date.now().toString();

  return `${execution}-${Math.random().toString(16).slice(2, 8)}`;
}
