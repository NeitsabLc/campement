import { expect, test } from '@playwright/test';
import { comptes, motDePasse, seConnecter, seDeconnecter } from './helpers.js';

test('une page protégée redirige un visiteur vers la connexion', async ({ page }) => {
  await page.goto('/stocks');

  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Ravi de vous revoir' })).toBeVisible();
});

test('des identifiants incorrects sont refusés', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Adresse e-mail').fill(`inconnu-e2e-${Date.now()}@example.test`);
  await page.getByLabel('Mot de passe', { exact: true }).fill(`${motDePasse}-incorrect`);
  await page.getByRole('button', { name: 'Se connecter' }).click();

  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('alert')).toHaveText('Identifiants incorrects.');
});

test('le mot de passe peut être affiché puis masqué', async ({ page }) => {
  await page.goto('/login');
  const champ = page.getByLabel('Mot de passe', { exact: true });
  const bouton = page.locator('.login-password__toggle');
  await expect(bouton).toHaveAccessibleName('Afficher le mot de passe');
  await champ.fill(motDePasse);

  await bouton.click();
  await expect(champ).toHaveAttribute('type', 'text');
  await expect(champ).toHaveValue(motDePasse);
  await expect(bouton).toHaveAccessibleName('Masquer le mot de passe');

  await bouton.click();
  await expect(champ).toHaveAttribute('type', 'password');
  await expect(champ).toHaveValue(motDePasse);
});

test('les informations légales sont centrées à côté de la navigation', async ({ page }) => {
  await seConnecter(page, comptes.gestionnaire);

  for (const chemin of ['/conditions-utilisation', '/politique-confidentialite']) {
    await page.goto(chemin);
    await expect(page.locator('body')).toHaveClass(/\blegal-shell\b/);
    await expect(page.locator('body')).toHaveClass(/\bapp-shell\b/);

    const ecartDeCentrage = await page.locator('.legal-page').evaluate((element) => {
      const navigation = document.querySelector('.sidebar').getBoundingClientRect();
      const pageLegale = element.getBoundingClientRect();
      const centreDisponible = navigation.right + ((window.innerWidth - navigation.right) / 2);
      const centrePage = pageLegale.left + (pageLegale.width / 2);

      return Math.abs(centreDisponible - centrePage);
    });
    expect(ecartDeCentrage).toBeLessThan(2);
    if ('/politique-confidentialite' === chemin) {
      await expect(page.locator('.legal-content')).toContainText('seuls les deux fichiers de journalisation les plus récents sont conservés');
      await expect(page.locator('.legal-content')).not.toContainText('fichiers rotés');
    }
  }
});

test('un administrateur peut se connecter puis se déconnecter', async ({ page }) => {
  await seConnecter(page, `  ${comptes.administrateur.toUpperCase()}  `);

  await expect(page.locator('.user-summary')).toContainText('Admin Campement');
  await expect(page.locator('.user-summary')).toContainText('ROLE_ADMIN');

  await seDeconnecter(page);
  await page.goto('/');
  await expect(page).toHaveURL(/\/login$/);
});
