# Configuration GitHub après migration

Aucune valeur secrète n'est versionnée. La CI de pull request et la publication
sur GHCR utilisent le `GITHUB_TOKEN` éphémère fourni par GitHub Actions.

## Secrets à créer

Créer ces secrets dans **Settings > Secrets and variables > Actions** :

| Secret | Usage | Accès minimal conseillé |
|---|---|---|
| `RELEASE_PLEASE_TOKEN` | créer ou actualiser `release/next`, ouvrir la PR, puis créer le tag et la GitHub Release | jeton fin de l'utilisateur `NeitsabLc`, limité à `campement`, avec Contents et Pull requests en lecture/écriture |
| `HOMELAB_DEPLOY_DISPATCH_TOKEN` | déclencher la recette et le workflow de production de `homelab-deploy` | jeton fin limité à `homelab-deploy`, avec Contents et Actions en lecture/écriture |

Le jeton de release doit appartenir au propriétaire du dépôt : le déploiement de
production vérifie l'auteur de la GitHub Release. Les cinq paquets
`campement-app-*` publiés sur GHCR doivent être publics, car le dépôt de
déploiement résout leurs digests avec un jeton de lecture anonyme.

## Protection de `main`

- exiger une pull request avant fusion ;
- conserver zéro approbation obligatoire tant que le dépôt repose sur un mainteneur unique ; le mainteneur doit relire le diff final après le dernier changement et avant la fusion ;
- exiger le contrôle **Qualité et tests** ;
- interdire les poussées forcées et la suppression de la branche ;
- activer le squash et utiliser le titre de la PR comme message du commit ;
- autoriser GitHub Actions à créer des pull requests dans les réglages Actions.

Les titres suivent Conventional Commits. `feat` produit une version mineure ;
`fix`, `perf`, `refactor`, `security` et `deps` une version corrective ; un
breaking change produit une version majeure. Les changements purement CI ne
déclenchent pas de version.

## Cycle de release

1. Lancer manuellement **Préparer ou publier une version** depuis `main`.
2. Le workflow crée ou actualise la PR `release/next` avec la version et le
   changelog calculés depuis le dernier tag.
3. Fusionner cette PR après validation de la CI.
4. Le commit de fusion crée la GitHub Release et le tag `vX.Y.Z`.
5. **Publier et déployer une version** construit les cinq images, ajoute SBOM et
   provenance, les signe avec Sigstore, teste leurs digests exacts, les promeut
   dans GHCR et déclenche la recette de `NeitsabLc/homelab-deploy`.

Une version existante peut être retestée et repromue depuis le lancement manuel
du workflow **Publier et déployer une version**.

La production reste manuelle : lancer **Promouvoir en production**, renseigner
la version et confirmer avec `production-VERSION`. Le dépôt `homelab-deploy`
applique ensuite ses propres protections d'environnement.

## Maintenance

Dependabot contrôle chaque semaine Composer, npm, les actions GitHub et les
images Docker. Les mises à jour mineures et correctives sont regroupées lorsque
cela réduit le bruit sans mélanger les écosystèmes.
