# Infra — Campement

> Fonctionnement général de la CI, des versions, des images et de la livraison.
> Dépôt public : machines, adresses, domaines, accès et état déployé sont dans la documentation
> d'exploitation privée (projet Infra Homelab), jamais ici.

## 01 Cycle complet

```text
branche ──PR──▶ CI ──squash──▶ main ──▶ PR Release Please ──fusion──▶ tag vX.Y.Z + GitHub Release
                                                                           │
                     ┌─────────────────────────────────────────────────────┘
                     ▼
     Publier et déployer une version : 5 images ▶ SBOM + provenance ▶ signature ▶ test par digest ▶ promotion GHCR
                     │
                     ▼ automatique
               RECETTE (dépôt de déploiement privé)
                     │
                     ▼ manuel : « Promouvoir en production » + confirmation production-VERSION
               PRODUCTION
```

## 02 Intégration continue (`ci.yml`, sur chaque PR vers `main`)

| Job | Contrôle |
|---|---|
| Titre conventionnel | Titre de PR au format Conventional Commits (workflow réutilisable) |
| Qualité rapide | Refus des versions PHP vulnérables, validation des versions et des configurations Compose |
| Secrets et dépendances | Trivy (vulnérabilités, secrets, configuration) publié dans GitHub Security ; Betterleaks |
| Validation Liquibase | Changelogs validés sur une base isolée |
| Tests applicatifs | Style, PHPStan, mapping Doctrine, PHPUnit |
| Tests navigateur | Playwright et Axe ; résultats conservés en cas d'échec |
| Configuration de production | Smoke test d'une installation de production jetable, sauvegarde et restauration |
| Image (matrice) | Analyse de sécurité de chaque image d'exécution |
| Qualite et tests | Job de synthèse exigé par la protection de `main` |

- Actions épinglées par SHA complet ; permissions en lecture par défaut.
- Dependabot chaque semaine : Composer, npm, GitHub Actions, images Docker.

## 03 Versions (Release Please)

- Chaque fusion dans `main` lance **Préparer ou publier une version** (`release.yml`).
- Release Please ouvre ou actualise une PR de version (numéro calculé depuis les commits ; met à jour `CHANGELOG.md`, `version.txt` et la version dans `app/config/services.yaml`).
- La fusion de cette PR crée le tag `vX.Y.Z` et la GitHub Release. Ne jamais créer de tag à la main.
- `feat` → mineure, `fix` → corrective, `!` → majeure ; sections du changelog dans `release-please-config.json`.

## 04 Images

| Image | Rôle |
|---|---|
| `campement-app-php` | Application Symfony (PHP-FPM) et commandes de maintenance |
| `campement-app-nginx` | Serveur web et assets compilés |
| `campement-app-postgres` | PostgreSQL 18 et rôles limités |
| `campement-app-liquibase` | Migrations du schéma |
| `campement-app-backup` | Sauvegardes chiffrées |

- Publiées dans GHCR, avec SBOM et provenance, signées sans clé (Cosign + OIDC GitHub).
- Toujours consommées **par digest**, jamais par tag ; vérification : `make release-verify`.
- Une version existante peut être retestée et repromue en relançant le workflow à la main (sans reconstruction).

## 05 Livraison

| Environnement | Déclenchement | Garde-fous |
|---|---|---|
| Recette | **Automatique** après la publication des images d'une release | Manifeste immuable (digests), migrations, contrôle HTTPS |
| Production | **Manuel** : workflow **Promouvoir en production**, version + saisie `production-VERSION` | Environnement GitHub protégé, sauvegarde restaurable avant migration, vérification des signatures |

- Le déploiement est exécuté par le dépôt de déploiement privé, sur un runner auto-hébergé ; ce dépôt ne fait que le déclencher.
- Une release publiée ne prouve pas son déploiement : vérifier le manifeste et l'état réel.
- Jamais de données de développement (`database/changelog/dev/`) en recette ou en production.

## 06 Secrets

- Valeurs locales dans les fichiers d'environnement ignorés par Git ; modèles : `.env.example`, `app/.env.example`.
- CI : `RELEASE_PLEASE_TOKEN` (PR de version, tag, release) et `HOMELAB_DEPLOY_DISPATCH_TOKEN` (déclenchement du déploiement) ;
  détail et droits minimaux dans `GITHUB_SETUP.md`. Les paquets GHCR `campement-app-*` sont publics.
- Sauvegardes chiffrées avec une clé publique ; la clé privée reste hors des serveurs et des archives.
- Ne documenter que des noms de variables, jamais de valeurs.

## 07 Parcours sur la recette déployée

- `make test-recette` (`npm run test:recette`, dossier `tests/recette/`) rejoue des parcours connectés sur la recette : lecture des pages du périmètre gestionnaire (refus propre d'une page réservée à l'administrateur), puis création, modification et annulation d'un mouvement de stock.
- Lancée à la main par `verificateur` en mode recette avant une promotion ; jamais en production, jamais en CI, et hors `test:e2e` / `test:browser` (suites distinctes, `testDir` différent).
- Variables obligatoires, sans valeur par défaut : `RECETTE_URL`, `RECETTE_EMAIL`, `RECETTE_MOT_DE_PASSE`. L'URL doit être en HTTPS et son nom d'hôte contenir `recette` ; la suite refuse aussi de démarrer si `CI` est défini. Ni trace, ni vidéo, ni capture d'écran (elles enregistreraient le mot de passe ou des données du séjour). En cas d'échec, Playwright écrit tout de même `test-results/recette/**/error-context.md` (dossier ignoré par Git) : la suite supprime l'instantané de page entière (`PLAYWRIGHT_NO_COPY_PROMPT`), mais le fichier garde le message d'erreur et l'extrait ARIA de l'élément testé, qui peuvent citer des libellés du séjour de contrôle. Aucun réglage ne supprime ce fichier ; ne pas le partager hors de la machine.
- Compte de contrôle : un utilisateur `ROLE_GESTIONNAIRE` dédié, gestionnaire d'un seul séjour actif nommé « Contrôle recette automatique », sans participant réel. Il est créé à la main en recette (jamais via `database/changelog/dev/`).
  - Sa première connexion se fait à la main avant le premier lancement, à cause du changement de mot de passe imposé (`premiere_connexion`).
  - Il ne doit jamais être affecté à un autre séjour ni utilisé à la main pendant la suite : la sélection du séjour est persistée sur l'utilisateur.
  - Son mot de passe reste hors dépôt, dans le trousseau macOS (service `campement-recette-controle`) :

```bash
security add-generic-password -a "$RECETTE_EMAIL" -s campement-recette-controle -w
RECETTE_MOT_DE_PASSE="$(security find-generic-password -a "$RECETTE_EMAIL" -s campement-recette-controle -w)" make test-recette
```

- Prérequis dans le séjour de contrôle, créés à la main : les modules Intendance et Administratif actifs (sinon l'application redirige vers le tableau de bord et les titres attendus ne correspondent plus), et au moins une denrée active avec son unité de référence. Aucun fournisseur n'est nécessaire.
- Prérequis hors séjour : l'origine de mouvement « Correction manuelle » (code `CORRECTION`) doit être présente et active. Les origines forment un référentiel global, commun à tous les séjours, fourni par les migrations versionnées ; elles ne se créent jamais à la main ni via `database/changelog/dev/`. La suite échoue si cette origine manque.
- Le test refuse d'écrire si le séjour sélectionné n'est pas exactement « Contrôle recette automatique » (vérifié avant chaque écriture).
- La suite **écrit puis nettoie** : un mouvement de stock ne se supprime pas, il s'annule. À la fin, et même après un échec, tout mouvement encore actif du séjour de contrôle est annulé (le séjour est dédié, aucun mouvement réel n'y est saisi), puis la suite vérifie qu'il n'en reste aucun d'actif. Les traces annulées (mouvement « Annulé », historique d'audit portant le repère « Contrôle recette automatique » dans les motifs) restent dans ce séjour : c'est attendu.
