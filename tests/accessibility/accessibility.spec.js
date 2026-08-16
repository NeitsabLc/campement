import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const impactsBloquants = new Set(['critical', 'serious']);

const pagesPubliques = [
  '/login',
  '/mot-de-passe-oublie',
  '/conditions-utilisation',
  '/politique-confidentialite',
  '/sortie-consommation',
];

const pagesAdministrateur = [
  '/',
  '/sejours',
  '/utilisateurs',
];

const pagesGestionnaire = [
  '/',
  '/groupes',
  '/fournisseurs',
  '/denrees',
  '/stocks',
  '/recettes',
  '/menus',
  '/intendance/distribution',
  '/administratif/participants',
  '/administratif/registre-presence',
  '/administratif/export',
  '/situations-particulieres',
];

function formaterViolations(violations) {
  return violations.map((violation) => ({
    id: violation.id,
    impact: violation.impact,
    aide: violation.help,
    url: violation.helpUrl,
    elements: violation.nodes.map((node) => node.target.join(' ')),
  }));
}

async function verifierPage(page, chemin) {
  const reponse = await page.goto(chemin);
  expect(reponse?.ok(), `La page ${chemin} doit répondre sans erreur`).toBeTruthy();
  expect(
    new URL(page.url()).pathname,
    `La page ${chemin} ne doit pas rediriger vers une autre page`,
  ).toBe(new URL(chemin, 'http://campement.local').pathname);

  const resultat = await new AxeBuilder({ page })
    .exclude('.sf-toolbar')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa'])
    .analyze();

  const violationsBloquantes = resultat.violations.filter((violation) =>
    impactsBloquants.has(violation.impact),
  );
  const violationsInformatives = resultat.violations.filter((violation) =>
    !impactsBloquants.has(violation.impact),
  );

  if (violationsInformatives.length > 0) {
    console.warn(
      `Violations non bloquantes sur ${chemin}:\n${JSON.stringify(formaterViolations(violationsInformatives), null, 2)}`,
    );
  }

  expect(
    violationsBloquantes,
    `Violations d’accessibilité bloquantes sur ${chemin}:\n${JSON.stringify(formaterViolations(violationsBloquantes), null, 2)}`,
  ).toEqual([]);
}

async function seConnecter(page, email) {
  await page.goto('/login');
  await page.getByLabel('Adresse e-mail').fill(email);
  await page.getByLabel('Mot de passe').fill('Campement?2026!');
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page).not.toHaveURL(/\/login$/);
}

test('le lien d’évitement déplace le focus vers le contenu principal', async ({ page }) => {
  await page.goto('/conditions-utilisation');

  const lien = page.getByRole('link', { name: 'Aller au contenu' });
  await page.keyboard.press('Tab');
  await expect(lien).toBeFocused();
  await expect(lien).toBeVisible();

  await page.keyboard.press('Enter');
  await expect(page.locator('#contenu-principal')).toBeFocused();
});

test('les pages publiques ne présentent pas de violation sérieuse ou critique', async ({ page }) => {
  for (const chemin of pagesPubliques) {
    await test.step(chemin, () => verifierPage(page, chemin));
  }
});

test('les pages d’administration ne présentent pas de violation sérieuse ou critique', async ({ page }) => {
  await seConnecter(page, 'admin@campement.local');

  for (const chemin of pagesAdministrateur) {
    await test.step(chemin, () => verifierPage(page, chemin));
  }
});

test('les pages de gestion ne présentent pas de violation sérieuse ou critique', async ({ page }) => {
  await seConnecter(page, 'gestionnaire@campement.local');

  for (const chemin of pagesGestionnaire) {
    await test.step(chemin, () => verifierPage(page, chemin));
  }
});
