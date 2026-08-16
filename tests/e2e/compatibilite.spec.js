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

test('@mobile le sélecteur recherché ne réactive pas la liste native après une sélection', async ({ page }) => {
  await page.setViewportSize({ width: 393, height: 851 });
  await seConnecter(page, comptes.gestionnaire);
  await page.goto('/stocks/mouvement');

  const field = page.locator('.movement-line-food').first();
  const nativeSelect = field.locator('select[data-line-food]');
  const trigger = field.locator('.searchable-select__trigger');
  await trigger.click();

  const firstOption = field.locator('.searchable-select__option').first();
  await expect(firstOption).toBeVisible();
  const optionLabel = (await firstOption.textContent()).trim();
  await field.locator('.searchable-select__search').fill(optionLabel);

  const option = field.getByRole('listbox').getByRole('option', { name: optionLabel, exact: true });
  const optionValue = await option.getAttribute('data-value');
  await option.evaluate((element) => {
    element.addEventListener('click', (event) => {
      window.searchableSelectClickDefaultPrevented = event.defaultPrevented;
    });
  });
  await option.click();

  await expect(nativeSelect).toHaveValue(optionValue);
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await expect.poll(() => page.evaluate(() => window.searchableSelectClickDefaultPrevented)).toBe(true);
});
