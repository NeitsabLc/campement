import { expect, test } from '@playwright/test';
import { comptes, seConnecter } from './helpers.js';

test('@compatibilite la connexion et la navigation fonctionnent dans Firefox', async ({ page }) => {
  await seConnecter(page, comptes.groupe);

  await page.getByRole('navigation', { name: 'Navigation principale' })
    .getByRole('link', { name: 'Menus', exact: true })
    .click();

  await expect(page).toHaveURL(/\/menus(?:\?|$)/);
  await expect(page.getByRole('heading', { name: 'Menus', exact: true })).toBeVisible();
});

test('@mobile un gestionnaire réalise le parcours critique vers les unités', async ({ page }) => {
  await page.setViewportSize({ width: 393, height: 851 });
  await seConnecter(page, comptes.gestionnaire);

  await page.getByRole('button', { name: 'Ouvrir le menu' }).click();
  await page.getByRole('navigation', { name: 'Navigation principale' })
    .getByRole('link', { name: 'Unités participantes', exact: true })
    .click();

  await expect(page).toHaveURL(/\/groupes$/);
  await expect(page.getByRole('heading', { name: 'Unités participantes', exact: true })).toBeVisible();
});
