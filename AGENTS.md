# AGENTS.md — Campement

> Consignes pour les agents IA (Claude Code, Codex…) qui travaillent sur ce dépôt.
> Les suivre garantit un code homogène, sûr et conforme au design system.

## 01 Objectif

Gérer les camps accompagnés des Scouts et Guides de France : séjours, unités, participants, dossiers
(documents, qualifications, stages pratiques) et intendance (menus, denrées, commandes, stocks).
Public : gestionnaires de séjour et responsables d'unité. Règles détaillées : [metier.md](docs/context/metier.md).

## 02 Avant de commencer

- [ ] Lire `git status --short` ; préserver les modifications locales existantes.
- [ ] Lire `docs/context/etat.md` (reprise, fichier local non versionné).
- [ ] Lire les documents utiles à la tâche :

| Besoin | Document |
|---|---|
| Stack, commandes, structure, données, performance | [architecture.md](docs/context/architecture.md) |
| CI, versions, images, recette et production | [infra.md](docs/context/infra.md) |
| Interface : couleurs, typo, icônes, messages | [design-system.md](docs/context/design-system.md) |
| Règles fonctionnelles | [metier.md](docs/context/metier.md) |
| Choix durables | [decisions.md](docs/context/decisions.md) |

- [ ] Utiliser le design system pour tout écran ou composant.
- [ ] Créer une branche dédiée depuis `main` à jour : `feature/…`, `fix/…`, `refactor/…`, `test/…`, `docs/…`, `chore/…`.

## 03 Règles générales

- [ ] Fichiers `.env` réels : lecture possible en local uniquement, jamais sur un serveur ; ne jamais recopier une valeur.
- [ ] Réutiliser les composants, services et gabarits existants avant d'en créer.
- [ ] Garder le code modulaire et scalable : une responsabilité par classe, service ou gabarit.
- [ ] Ne pas créer de fichier non nécessaire (pas de doc ou script jetable dans le dépôt).
- [ ] Dépôt public : aucun détail d'infrastructure (IP, machines, domaines privés, chemins locaux).
- [ ] Répondre en français ; ne pas pousser ni fusionner sans accord explicite.

## 04 Guidelines de développement

- [ ] Composants fonctionnels : contrôleurs fins, règles métier dans `app/src/Service/` et les entités.
- [ ] Nommage simple et compréhensible, en français comme le code existant (`ContexteSejour`, `FormulaireParticipant`).
- [ ] Composants petits : extraire un service, un composant Twig ou un contrôleur Stimulus dès qu'un bloc grossit.
- [ ] Accessibilité WCAG 2.2 AA / RGAA : libellés, contrastes, navigation clavier, focus visible, rôles ARIA.
- [ ] Textes d'interface en français.
- [ ] Toute lecture ou écriture métier respecte le séjour courant et les habilitations côté serveur.

## 05 Sécurité

- [ ] Ne jamais exposer de clé, mot de passe ou jeton (code, logs, tests, docs, Git).
- [ ] Toute configuration sensible passe par une variable d'environnement, documentée dans `.env.example`.
- [ ] Valider toutes les entrées utilisateur côté serveur (Validator, jeton CSRF, contrôle d'accès).
- [ ] Gérer les erreurs : message clair à l'utilisateur, aucune trace technique exposée, journal sans donnée personnelle.
- [ ] Données de développement uniquement dans `database/changelog/dev/`, jamais en recette ou en production.

## 06 Commandes utiles

```bash
make help                 # inventaire complet
make install              # première installation (après copie des .env.example)
make up / make ps         # démarrer / état
make db-status            # migrations en attente
make test                 # tests PHP (recrée la base de tests)
make test-accessibility   # Axe ; make test-e2e pour les parcours
make style analyse-statique doctrine-validate
```

## 07 Pièges connus

- Ne jamais modifier un changeset Liquibase appliqué ; ne jamais lancer `doctrine:schema:update --force`.
- `make test` recrée la base de tests ; vérifier la base visée avant les tests navigateur.
- `make reset`, suppressions de volumes, purges et restaurations : accord explicite obligatoire.
- `ContexteSejour` et `PerimetreUtilisateur` centralisent le périmètre ; ne pas les contourner.
- Les documents participants font partie du périmètre de sauvegarde, avec la base.

## 08 Après le développement

- [ ] Tests : `make test`, plus `make test-accessibility` / `make test-e2e` si l'interface change.
- [ ] Qualité : `make style analyse-statique doctrine-validate` et `git diff --check`.
- [ ] Sécurité : `make composer ARGS="audit"`, `npm audit --audit-level=high`, `gitleaks git --log-opts="origin/main..HEAD"` ;
      la CI ajoute Trivy et Betterleaks.
- [ ] Commits Conventional Commits, description en français : `feat(menus): ajouter la variante sans lactose`.
- [ ] Avant toute PR : `git fetch origin && git rebase origin/main`, puis relancer les tests.
      Une branche déjà poussée ne se repousse qu'avec accord (`--force-with-lease`).
- [ ] Mettre à jour `etat.md` et la doc concernée ; indiquer contrôles réalisés et limites.

## 09 Besoin d'aide ?

- Chercher dans `docs/context/`, puis dans le code existant un cas similaire.
- Demander avant une action destructive, une restauration, un changement de production ou une refonte.
- `CLAUDE.md` contient seulement `@AGENTS.md` ; ce fichier reste la source unique des consignes.
