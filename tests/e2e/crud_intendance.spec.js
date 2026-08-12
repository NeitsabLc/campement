import { expect, test } from '@playwright/test';
import { comptes, seConnecter, selectionnerSejour, suffixeUnique } from './helpers.js';

const sejourPrincipal = 'Séjour de développement';

test.beforeEach(async ({ page }) => {
  await seConnecter(page, comptes.gestionnaire);
  await selectionnerSejour(page, sejourPrincipal);
});

async function creerFournisseur(page, suffixe) {
  const nom = `Fournisseur E2E ${suffixe}`;
  await page.goto('/fournisseurs/ajouter');
  await page.locator('#supplier-name').fill(nom);
  await page.locator('#supplier-phone').fill('0102030405');
  await page.locator('#supplier-email').fill(`fournisseur-${suffixe}@example.test`);
  await page.locator('#supplier-address').fill('1 chemin des tests');
  await page.getByRole('button', { name: 'Enregistrer le fournisseur' }).click();
  await expect(page).toHaveURL(/\/fournisseurs$/);
  await expect(page.getByRole('status')).toContainText(`Le fournisseur « ${nom} » a bien été créé.`);

  return nom;
}

async function creerDenree(page, suffixe) {
  const fournisseur = await creerFournisseur(page, suffixe);
  const nom = `Denrée E2E ${suffixe}`;
  await page.goto('/denrees/ajouter');
  await page.locator('input[name="nom"]').fill(nom);
  await page.locator('select[name="unite_inventaire"]').selectOption({ label: 'kilogramme' });
  const blocFournisseur = page.locator('.supplier-card').first();
  await blocFournisseur.locator('select[data-field="fournisseur"]').selectOption({ label: fournisseur });
  await blocFournisseur.locator('select[data-level-field="conditionnement"]').selectOption({ label: 'kilogramme' });
  await page.locator('input[name="stock_min"]').fill('5');
  await page.getByRole('button', { name: 'Enregistrer la denrée' }).click();
  await expect(page).toHaveURL(/\/denrees$/);
  await expect(page.getByRole('status')).toContainText(`La denrée « ${nom} » a bien été créée.`);

  return { fournisseur, nom };
}

async function enregistrerRepasSpecial(page) {
  const rechargement = page.waitForResponse((reponse) => {
    const url = new URL(reponse.url());

    return 'GET' === reponse.request().method()
      && '/menus' === url.pathname
      && 'EXPLO' === url.searchParams.get('special')
      && 200 === reponse.status();
  });
  await page.getByRole('button', { name: 'Enregistrer ce repas' }).click();
  await rechargement;
  await expect(page.getByRole('status')).toContainText('Le repas a bien été enregistré.');
}

test('un fournisseur peut être créé, consulté, modifié, désactivé puis réactivé', async ({ page }) => {
  const suffixe = suffixeUnique();
  const nom = await creerFournisseur(page, suffixe);
  const nomModifie = `${nom} modifié`;

  await page.locator(`a.edit-supplier-button[aria-label="Modifier ${nom}"]`).click();
  await page.locator('#supplier-name').fill(nomModifie);
  await page.locator('#supplier-phone').fill('0504030201');
  await page.getByRole('button', { name: 'Enregistrer le fournisseur' }).click();
  await expect(page.getByRole('status')).toContainText(`Le fournisseur « ${nomModifie} » a bien été modifié.`);

  await page.getByRole('button', { name: `Désactiver ${nomModifie}` }).click();
  await page.getByRole('dialog', { name: 'Désactiver ce fournisseur ?' })
    .getByRole('button', { name: 'Désactiver le fournisseur' }).click();
  await expect(page.getByRole('status')).toContainText(`Le fournisseur « ${nomModifie} » a bien été désactivé.`);

  await page.getByRole('link', { name: 'Désactivés' }).click();
  const ligneInactive = page.locator('.supplier-row--inactive').filter({ hasText: nomModifie });
  await expect(ligneInactive).toContainText('Inactif');
  await ligneInactive.getByRole('button', { name: `Réactiver ${nomModifie}` }).click();
  await expect(page.getByRole('status')).toContainText(`Le fournisseur « ${nomModifie} » a bien été réactivé.`);
});

test('une denrée peut être créée, consultée, modifiée, désactivée puis réactivée', async ({ page }) => {
  const suffixe = suffixeUnique();
  const { nom } = await creerDenree(page, suffixe);
  const nomModifie = `${nom} modifiée`;

  await page.locator(`a.action-icon-button[aria-label="Modifier ${nom}"]`).click();
  await page.locator('input[name="nom"]').fill(nomModifie);
  await page.locator('input[name="stock_min"]').fill('8');
  await page.getByRole('button', { name: 'Enregistrer la denrée' }).click();
  await expect(page.getByRole('status')).toContainText(`La denrée « ${nomModifie} » a bien été modifiée.`);

  const ligne = page.locator('[data-food-catalog-target="row"]').filter({ hasText: nomModifie });
  await ligne.locator('.food-actions').getByRole('button', { name: `Désactiver ${nomModifie}` }).click();
  await expect(page).toHaveURL(/\/denrees\?desactivees=1$/);
  const ligneInactive = page.locator('[data-food-catalog-target="row"]').filter({ hasText: nomModifie });
  await expect(ligneInactive).toContainText('Désactivée');
  await ligneInactive.locator('.food-actions').getByRole('button', { name: 'Réactiver' }).click();
  await expect(page.getByRole('status')).toContainText(`La denrée « ${nomModifie} » a bien été réactivée.`);
});

test('une recette peut être créée, consultée, modifiée, désactivée puis réactivée', async ({ page }) => {
  const suffixe = suffixeUnique();
  const { nom: denree } = await creerDenree(page, suffixe);
  const nom = `Recette E2E ${suffixe}`;
  const nomModifie = `${nom} modifiée`;

  await page.goto('/recettes/ajouter');
  await page.locator('input[name="nom"]').fill(nom);
  await page.locator('select[name="categorie"]').selectOption('PLAT');
  await page.getByRole('button', { name: /Ajouter une denrée/ }).click();
  const ligne = page.locator('[data-recipe-editor-target="rows"] .recipe-row').first();
  await ligne.locator('select[data-field="denree"]').selectOption({ label: denree });
  await expect(ligne.locator('select[data-field="conditionnement"] option')).not.toHaveCount(0);
  await page.getByRole('button', { name: 'Enregistrer la recette' }).click();
  await expect(page).toHaveURL(/\/recettes$/);
  await expect(page.getByRole('status')).toContainText('La recette a bien été enregistrée.');

  await page.locator(`a.action-icon-button[aria-label="Modifier ${nom}"]`).click();
  await page.locator('input[name="nom"]').fill(nomModifie);
  await page.locator('select[name="categorie"]').selectOption('DESSERT');
  await page.getByRole('button', { name: 'Enregistrer la recette' }).click();
  await expect(page.getByRole('status')).toContainText('La recette a bien été enregistrée.');

  let ligneRecette = page.locator('[data-food-catalog-target="row"]').filter({ hasText: nomModifie });
  await ligneRecette.locator('.food-actions').getByRole('button', { name: `Désactiver ${nomModifie}` }).click();
  await expect(page).toHaveURL(/\/recettes\?desactivees=1$/);
  ligneRecette = page.locator('[data-food-catalog-target="row"]').filter({ hasText: nomModifie });
  await ligneRecette.locator('.food-actions').getByRole('button', { name: `Réactiver ${nomModifie}` }).click();
  await expect(page.getByRole('status')).toContainText(`La recette « ${nomModifie} » a été réactivée.`);
});

test('un menu peut être créé, consulté, modifié puis vidé', async ({ page }) => {
  const suffixe = suffixeUnique();
  const { nom: denree } = await creerDenree(page, suffixe);

  await page.goto('/menus?special=EXPLO');
  const bloc = page.locator('[data-menu-block]').first();
  const ajoutDenree = bloc.locator('.menu-adder').filter({ hasText: 'Ajouter une denrée' });
  await ajoutDenree.locator('select[data-food-picker]').selectOption({ label: denree });
  await ajoutDenree.getByRole('button', { name: 'Ajouter' }).click();
  const ligne = bloc.locator('[data-line]').filter({ hasText: denree });
  await expect(ligne).toBeVisible();
  await ligne.locator('input[data-public]').first().fill('2');
  await enregistrerRepasSpecial(page);
  await expect(page).toHaveURL(/\/menus\?special=EXPLO$/);
  await expect(page.locator('[data-line]').filter({ hasText: denree })).toBeVisible();

  const ligneModifiee = page.locator('[data-line]').filter({ hasText: denree });
  await ligneModifiee.locator('input[data-public]').first().fill('3');
  await enregistrerRepasSpecial(page);
  await expect(page.locator('[data-line]').filter({ hasText: denree }).locator('input[data-public]').first()).toHaveValue(/3/);

  await page.getByRole('button', { name: `Supprimer ${denree}` }).click();
  await expect(page.locator('[data-line]').filter({ hasText: denree })).toHaveCount(0);
  await enregistrerRepasSpecial(page);
  await expect(page.locator('[data-line]').filter({ hasText: denree })).toHaveCount(0);
});
