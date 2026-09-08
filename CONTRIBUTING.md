# Contribuer à Campement

## Gestion des branches

- `main` contient les versions stables et publiées ; aucun commit direct n’y est autorisé.
- Toute branche de travail part de `main`, reste courte et ne traite qu’un sujet.
- Utiliser un nom explicite : `feature/description`, `fix/description`, `refactor/description`, `test/description`, `docs/description` ou `chore/description`.
- Mettre régulièrement la branche à jour depuis `main` et résoudre les conflits avant la revue.
- Ouvrir une pull request vers `main`, faire valider la CI et obtenir une revue avant fusion.
- Supprimer la branche après sa fusion.
- Ne pas créer manuellement de tag de version : Release Please gère les versions et les tags `vX.Y.Z`.

## Gestion des commits

Les messages et les titres de pull request suivent Conventional Commits :

```text
<type>(<portée optionnelle>): <description>
```

Types autorisés : `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `build`, `ci`, `chore`, `style`, `security` et `revert`.

- écrire une description courte, précise et à l’impératif ;
- créer des commits atomiques : un changement logique par commit ;
- utiliser `feat!:` ou `type(portée)!:` pour une rupture de compatibilité et la décrire dans le corps du commit ;
- référencer l’issue concernée dans le corps du commit ou de la pull request ;
- ne jamais commiter de secret, fichier `.env`, sauvegarde, export de production ou artefact généré ;
- exécuter les tests et contrôles utiles avant de pousser ;
- corriger ou regrouper les commits de travail avant la fusion lorsque la revue le demande.

Exemples :

```text
feat(menus): ajouter la variante sans lactose
fix(stock): empêcher un mouvement négatif
test(inscriptions): couvrir le refus d’un doublon
ci: mettre à jour le contrôle des images
```
