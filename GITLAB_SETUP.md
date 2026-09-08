# Configuration GitLab après migration

Aucune valeur secrète n'est versionnée. Toutes les variables ci-dessous doivent
être créées manuellement dans **Settings > CI/CD > Variables**.

## Variables à créer

| Variable | Usage | Protection conseillée |
|---|---|---|
| `GITLAB_TOKEN` | création du commit, du tag et de la GitLab Release par semantic-release | jeton d'accès projet de rôle Maintainer, masqué et protégé, scope `api` |
| `RENOVATE_GITLAB_TOKEN` | création des MR de mise à jour des dépendances | masquée et protégée, scopes API et écriture du dépôt |

Ne pas exposer ces variables aux pipelines de merge request. Le scan Betterleaks
de la MR et les builds ordinaires fonctionnent sans ces secrets.

Ne pas activer **Allow Git push requests to the repository** pour le
`CI_JOB_TOKEN` : semantic-release doit pousser avec `GITLAB_TOKEN`, afin que le
tag déclenche bien son pipeline de publication.

## Releases regroupées

Le job `semantic-release` n'est pas exécuté après chaque merge. Il peut être :

- lancé manuellement depuis un pipeline de `main` ;
- lancé par un planning avec la variable `RUN_RELEASE=true`.

Il analyse tous les commits conventionnels depuis le dernier tag `vX.Y.Z`.
Plusieurs merge requests fusionnées sont donc regroupées dans une seule version.
`feat` produit une version mineure, `fix`, `perf`, `refactor`,
`security` et `deps` une version corrective, et un breaking change une
version majeure.

Le tag déclenche ensuite la construction des cinq images, leur signature
Sigstore, le smoke test des digests candidats, leur promotion sans reconstruction
et le pipeline recette de `neitsablc/homelab-deploy`.

Une version existante peut être retestée et repromue sans reconstruction en
lançant un pipeline de `main` avec `REPROMOTE_VERSION=X.Y.Z`.

## Permissions inter-projets

- Autoriser `neitsablc/campement` à déclencher `neitsablc/homelab-deploy`.
- Dans ce projet, autoriser le `CI_JOB_TOKEN` de
  `neitsablc/homelab-deploy` à lire les tags, releases, fichiers et images.
- Protéger `main`, les tags `v*`, les tags de registre `sha-*` et les
  versions sémantiques.
- Exiger un pipeline réussi avant fusion.
- Activer le squash des merge requests et utiliser le titre de la MR comme
  message du commit squash. Le contrôle de titre garantit ainsi que le commit
  lu par semantic-release reste conventionnel.

## Plannings facultatifs

Créer des plannings distincts sur `main` :

- `RUN_RENOVATE=true` pour Renovate ;
- `RUN_RELEASE=true` uniquement à la fréquence de publication souhaitée.
