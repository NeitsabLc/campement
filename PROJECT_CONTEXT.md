# Campement — Contexte du projet

## 1. Objectif et état du projet

Campement est une application web de gestion des camps accompagnés. Elle est
destinée principalement aux équipes pilotes et permet également aux unités
participantes de saisir leurs distributions de denrées.

L’application couvre actuellement :

* l’authentification et la gestion des mots de passe ;
* l’administration des utilisateurs, rôles et accès ;
* la création, l’activation et la sélection de plusieurs séjours ;
* la gestion des unités participantes, de leurs besoins alimentaires, des
  participants, de leurs documents et de leurs présences ;
* le suivi des situations particulières et des tâches associées ;
* la gestion des recettes, des menus, des régimes alimentaires et des quantités
  individuelles par public cible ;
* la gestion des denrées, fournisseurs, références et conditionnements ;
* la saisie et la consultation des mouvements de stock ;
* la distribution publique sécurisée par un lien propre à chaque séjour ;
* l’envoi d’e-mails de création de compte et de réinitialisation de mot de passe ;
* les exports PDF et les règles automatiques de conservation des données.

État vérifié le 21 août 2026 : version applicative `v1.4.0` préparée, dernier
tag stable `v1.3.1`, branche stable `main`, branche de développement `dev`,
schéma Liquibase courant `V034`. La production est hébergée sur un hôte
applicatif dédié derrière un reverse proxy Traefik séparé ; l’ancienne
installation est arrêtée et conservée pour le retour arrière. Le projet est
fonctionnel. Toute évolution doit
préserver les données existantes et rester compatible avec les schémas déjà
appliqués.

## 2. Organisation du dépôt

```text
campement/
├── .github/
│   ├── dependabot.yml
│   └── workflows/
│       ├── ci.yaml
│       └── publish-images.yaml
├── .dockerignore
├── compose.yaml
├── compose.prod.yaml
├── CHANGELOG.md
├── Makefile
├── PROJECT_CONTEXT.md
├── scripts/
│   └── ci-production-smoke.sh
├── docker/
│   ├── nginx/
│   ├── liquibase/
│   ├── php/
│   └── postgres/
├── database/
│   └── changelog/
│       ├── db.changelog-master.yaml
│       ├── versioned/        # changements communs à tous les environnements
│       │   └── V001...V034
│       └── dev/              # données réservées au développement et aux tests
│           └── D000...D006
└── app/
    ├── assets/               # JavaScript, Stimulus, CSS, images et polices
    ├── bin/
    ├── config/
    ├── public/
    ├── src/
    │   ├── Controller/
    │   ├── Doctrine/
    │   ├── Entity/
    │   ├── Enum/
    │   ├── Repository/
    │   ├── Security/
    │   ├── Service/
    │   └── Twig/
    ├── templates/
    ├── tests/
    └── composer.json
```

Le code Symfony se trouve dans `app/`.

Le répertoire local `.local/` est ignoré par Git. Il peut contenir les notes
d’exploitation propres à une installation, mais aucune information qu’un clone
du dépôt devrait recevoir.

Les changesets `versioned/` s’appliquent à tous les environnements. Les fichiers
`dev/` fournissent uniquement les données de développement et de test lorsque le
contexte Liquibase `dev` est activé.

## 3. Stack technique

* PHP 8.4 ;
* Symfony 8.1 ;
* Doctrine ORM ;
* PostgreSQL 18 ;
* Liquibase ;
* Docker Compose ;
* Nginx ;
* UUID version 7 ;
* Twig, AssetMapper, Turbo et Stimulus ;
* PHPUnit.

## 4. Conventions d’interface

### Messages de confirmation

Tous les messages de confirmation positifs disparaissent automatiquement trois
secondes après leur affichage. Utiliser la classe globale `flash--success` ; le
comportement est géré dans `app/assets/app.js` et fonctionne aussi après une
navigation Turbo.

Les messages d’erreur et d’avertissement ne disparaissent pas automatiquement,
afin que l’utilisateur puisse les lire et corriger le formulaire.

### Chargement et compilation des styles

La feuille de styles principale doit toujours être chargée explicitement dans
`app/templates/base.html.twig` :

```twig
{% block stylesheets %}
    <link rel="stylesheet" href="{{ asset('styles/app.css') }}">
{% endblock %}
```

Ne pas charger `styles/app.css` uniquement avec un `import` depuis
`app/assets/app.js`. Ce chargement indirect a déjà produit des pages sans style
à cause de la résolution des dépendances et du cache d’AssetMapper.

En développement, ne pas compiler les assets : AssetMapper sert directement les
sources et reflète immédiatement leurs modifications. Le conteneur PHP supprime
automatiquement `app/public/assets` à son démarrage pour éviter qu’une ancienne
compilation masque les sources à jour.

En environnement compilé (`APP_ENV=prod`), les assets sont installés et
compilés pendant la construction multi-stage de l’image. Le démarrage de
PHP-FPM n’effectue aucun téléchargement ni compilation. Le répertoire public
compilé est copié dans l’image Nginx ; les URLs générées par `asset()`
contiennent l’empreinte du contenu, sans suffixe de version manuel dans les
gabarits.

Pour contrôler un asset :

1. vérifier que `debug:asset-map` contient la ressource modifiée ;
2. vérifier que le HTML rendu charge bien la feuille attendue ;
3. vérifier que l’URL générée répond avec un statut HTTP 200 ;
4. contrôler une règle propre à la page modifiée.

### Navigation

L’interface authentifiée utilise une barre latérale repliable et adaptée aux
écrans mobiles. Elle contient notamment :

* Accueil ;
* le séjour actif ;
* Unités participantes ;
* la section Intendance lorsque le module est actif ;
* Utilisateurs ;
* Gestion des séjours ;
* le résumé de l’utilisateur connecté et la déconnexion.

## 5. Responsabilité des outils de persistance

### Liquibase

Liquibase constitue l’unique source de vérité du schéma PostgreSQL.

Toutes les créations et évolutions de tables, colonnes, contraintes, index et
données initiales passent par des changesets Liquibase.

Le schéma pouvant déjà être appliqué sur des environnements contenant des
données :

* un changeset versionné déjà appliqué ne doit plus être modifié ;
* toute nouvelle évolution utilise un nouveau fichier `Vxxx` ;
* les données de démonstration ou de test restent dans `database/changelog/dev/` ;
* une sauvegarde PostgreSQL restaurable doit précéder toute mise à jour d’un
  environnement contenant des données à conserver ;
* `make reset` et la suppression des volumes sont réservés aux environnements
  locaux jetables.

### Doctrine

Doctrine est utilisé pour :

* mapper les tables vers les entités ;
* gérer les associations ;
* utiliser les repositories ;
* valider le mapping ;
* construire et exécuter les requêtes applicatives.

Doctrine ne doit jamais modifier le schéma. Les commandes suivantes ne doivent
jamais être exécutées :

```bash
php bin/console doctrine:schema:update --force
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

La validation du mapping se fait avec :

```bash
make doctrine-validate
```

ou :

```bash
make console ARGS="doctrine:schema:validate --skip-sync"
```

## 6. Gestion des identifiants

Toutes les clés primaires métier utilisent un UUID version 7. L’application les
génère avant la persistance ; les defaults PostgreSQL restent présents pour les
imports et écritures SQL directes :

```sql
id UUID NOT NULL DEFAULT uuidv7()
```

Le default PostgreSQL reste déclaré uniquement dans Liquibase. Le mapping
Doctrine déclare seulement l’identifiant :

```php
#[ORM\Id]
#[ORM\Column(type: 'uuid')]
private Uuid $id;
```

Il est normal que la commande de comparaison Doctrine propose de supprimer ces
defaults :

```bash
make console ARGS="doctrine:schema:update --dump-sql"
```

Exemple d’écart volontaire :

```sql
ALTER TABLE ... ALTER id DROP DEFAULT;
```

Cette sortie peut être consultée à titre diagnostique, mais ne doit jamais être
exécutée.

## 7. Fonctionnalités actuelles

### Administration

L’application permet de gérer :

* les utilisateurs, leur rôle et leur statut ;
* leurs rattachements aux séjours ou à une unité participante ;
* les séjours, leurs dates, leurs gestionnaires et leurs modules ;
* les types de repas et publics cibles utilisés par chaque séjour ;
* les unités participantes, leurs effectifs et leurs besoins alimentaires.

### Intendance

Pour le séjour sélectionné, le module Intendance permet de gérer :

* les fournisseurs ;
* les denrées et leurs unités de référence ;
* les références fournisseur et leurs conditionnements ;
* les recettes, les menus, leurs variantes alimentaires et les quantités
  individuelles par public ;
* les entrées, sorties et corrections de stock ;
* la consultation des mouvements ;
* le lien et le QR code de distribution publique ;
* les archives PDF de listes de courses, limitées à une période choisie.

### Référentiels globaux

Les unités, types de repas, types de mouvements et publics cibles constituent des
référentiels globaux alimentés par Liquibase. Leur utilisation effective est
ensuite configurée ou filtrée par séjour selon le modèle métier.

## 8. Utilisateurs, rôles et accès

Les rôles métier sont :

* `ROLE_ADMIN` ;
* `ROLE_GESTIONNAIRE` ;
* `ROLE_GROUPE` ;
* `ROLE_TECHNIQUE`, réservé au compte de saisie publique.

`ROLE_ADMIN` hérite de `ROLE_GESTIONNAIRE`.

### Administrateur

Un administrateur peut accéder à tous les séjours et administrer l’ensemble des
utilisateurs et affectations.

### Gestionnaire

Un gestionnaire peut être associé à plusieurs séjours via `utilisateur_sejour`.
Il ne peut travailler que sur les séjours actifs auxquels il est affecté, sauf
les possibilités supplémentaires accordées aux administrateurs.

### Utilisateur d’une unité participante

Un utilisateur `ROLE_GROUPE` est associé à zéro ou une unité au moyen de
`utilisateur.groupe_id`. Plusieurs utilisateurs peuvent être associés à la même
unité. Son accès à la distribution est lié au séjour de cette unité.

### Compte technique

Les mouvements issus du formulaire public sont associés à l’utilisateur :

```text
saisie-consommation@campement.local
```

Ce compte :

* utilise `ROLE_TECHNIQUE` ;
* n’est pas autorisé à s’authentifier de manière interactive ;
* permet de conserver `mouvement_stock.utilisateur_id` obligatoire ;
* identifie les mouvements issus du parcours public.

### Authentification et mots de passe

Les utilisateurs désactivés et le compte technique ne peuvent pas se connecter.
L’application gère :

* la connexion et la déconnexion par requête POST protégée par CSRF ;
* l’obligation éventuelle de modifier son mot de passe ;
* l’envoi d’un e-mail lors de la création d’un utilisateur ;
* la demande de réinitialisation de mot de passe ;
* un jeton de réinitialisation temporaire et à usage unique.

## 9. Contexte multi-séjour

L’application gère plusieurs séjours. Le service `ContexteSejour` centralise les
séjours accessibles et le séjour courant.

Le dernier séjour sélectionné est mémorisé par `utilisateur.dernier_sejour_id`.
La sélection est corrigée automatiquement si ce séjour devient inactif ou
inaccessible.

Les règles transversales sont les suivantes :

* un administrateur accède à tous les séjours ;
* un gestionnaire accède uniquement aux séjours auxquels il est affecté ;
* les pages métier nécessitent un séjour courant accessible ;
* le module Intendance doit être actif pour accéder aux pages correspondantes ;
* toutes les listes et écritures métier sont filtrées par le séjour courant ;
* toute ressource reçue depuis le navigateur doit être vérifiée côté serveur afin
  de confirmer son appartenance au séjour courant.

Il ne faut jamais déduire l’autorisation d’un identifiant ou d’un champ caché
envoyé par le navigateur.

## 10. Modèle métier

### Entités principales

Le modèle comprend notamment :

* `Sejour`, `TypeRepas`, `SejourTypeRepas` ;
* `Groupe` ;
* `Menu`, `MenuDenree`, `MenuDenreeQuantite` ;
* `PublicCible`, `SejourPublicCible` ;
* `Unite`, `Denree` ;
* `Fournisseur`, `ReferenceFournisseur`,
  `ReferenceFournisseurConditionnement` ;
* `TypeMouvement`, `OrigineMouvement` ;
* `MouvementStock`, `MouvementStockLigne`,
  `MouvementStockLigneConditionnement` ;
* `Utilisateur`.

### Séjours et types de repas

```text
sejour
  └── sejour_type_repas
        └── type_repas
```

`sejour_type_repas` configure les types de repas disponibles et leur ordre pour
chaque séjour.

Un menu référence un séjour, un `sejour_type_repas`, une date, éventuellement un
nom et un commentaire, ainsi qu’un statut actif. Il ne référence pas directement
`type_repas`.

### Unités participantes

Une unité participante appartient à un séjour. Elle possède notamment un nom,
un effectif jeune, un effectif adulte, des dates de présence et un statut actif.
Lorsque le module Intendance est actif, trois compteurs indiquent le nombre de
personnes végétariennes, sans lactose et sans gluten. Chaque compteur est positif
ou nul et ne peut pas dépasser l’effectif total ; une même personne peut relever
de plusieurs besoins.

Les effectifs décrivent l’unité mais ne sont pas utilisés automatiquement pour
calculer les quantités du menu. Ils sont indépendants des publics cibles.

### Publics cibles

`public_cible` est le référentiel global des publics utilisés pour définir les
portions individuelles :

* `LOUVETEAUX_JEANNETTES` — Louveteaux-Jeannettes ;
* `SCOUTS_GUIDES` — Scouts-Guides ;
* `PIONNIERS_CARAVELLES` — Pionniers-Caravelles ;
* `ADULTE` — Adulte.

`sejour_public_cible` sélectionne les publics utilisés par chaque séjour et
porte leur statut actif pour ce séjour. L’ordre d’affichage est déterminé par le
référentiel global.

Les pages de menu et de distribution n’affichent que les associations actives du
séjour dont le public global est lui-même actif. Un public absent ou désactivé
n’est pas proposé, même lorsqu’une ancienne quantité de menu le référence.

### Composition des menus

```text
menu
  └── menu_denree
        └── menu_denree_quantite
              └── sejour_public_cible
                    └── public_cible
```

`menu_denree` associe une denrée à un menu et porte son ordre d’affichage, son
conditionnement, sa catégorie éventuelle, son origine recette et un régime
alimentaire facultatif. Les valeurs admises sont `VEGETARIEN`, `SANS_LACTOSE`
et `SANS_GLUTEN` ; l’absence de valeur représente la ligne standard. Une même
denrée peut donc apparaître en ligne standard et dans une ou plusieurs variantes.

`menu_denree_quantite` porte une quantité individuelle exprimée dans le
conditionnement choisi pour un public cible donné. Une seule quantité peut être
définie pour le couple `menu_denree + sejour_public_cible`.

Ces quantités sont informatives. L’application ne calcule pas automatiquement
une quantité totale à partir des effectifs de l’unité.

Les lignes de `recette_denree` portent le même régime facultatif. À l’ajout
d’une recette dans un menu, quantité, conditionnement et régime sont copiés dans
les lignes du menu. Leur modification reste locale au menu. L’action de
resynchronisation remplace ces valeurs par celles de la recette, sans modifier
la recette source. Les denrées ajoutées hors recette peuvent également recevoir
un régime.

### Denrées et fournisseurs

Une denrée appartient obligatoirement à un séjour et possède une unité de
référence globale. Son nom est unique au sein de ce séjour.

Un fournisseur appartient obligatoirement à un séjour et son nom y est unique.
Une référence fournisseur relie un fournisseur, une denrée, une référence
commerciale et une désignation. Elle peut posséder plusieurs conditionnements.

Le fournisseur et la denrée d’une référence doivent appartenir au même séjour.
Les menus et mouvements ne peuvent utiliser que des denrées de leur propre
séjour.

## 11. Distribution publique

La distribution publique est accessible par une route contenant un jeton UUID
propre au séjour :

```text
/distribution/{jeton}
```

Le gestionnaire peut consulter le lien absolu, afficher son QR code et régénérer
le jeton. Toute régénération invalide immédiatement l’ancien lien. Le jeton est
également renouvelé automatiquement lorsqu’un séjour désactivé est réactivé.

Le lien public est fermé automatiquement après le dernier jour du séjour. Il
reste utilisable pendant toute la date de fin, sous réserve que le séjour, le
module intendance et la distribution publique soient actifs.

Le séjour est toujours déduit du jeton. Il ne dépend ni de la session d’un
utilisateur connecté ni d’un identifiant de séjour envoyé librement par le
navigateur. Les soumissions POST publiques sont soumises à une limitation de
fréquence. Une consultation GET ne doit jamais créer ou modifier un menu.

Le parcours est le suivant :

1. sélection de l’unité participante ;
2. sélection du jour ;
3. sélection du repas ;
4. chargement du menu correspondant ;
5. affichage de ses denrées et quantités individuelles, en retirant les variantes
   dont le compteur est nul pour l’unité ;
6. saisie de la quantité totale réellement prise pour chaque denrée ;
7. validation définitive du mouvement.

Le premier formulaire génère une clé UUID de soumission, conservée pendant
l’écran de confirmation. Une contrainte unique sur
`mouvement_stock.cle_soumission` rend la confirmation idempotente et protège
aussi contre deux requêtes concurrentes.

Lorsque la distribution est indisponible, la page publique distingue l’absence
de menu configuré de l’absence d’unité présente à la date du jour. Les dates et
repas proposés sont uniquement ceux pour lesquels un menu actif existe.

L’application ne combine pas automatiquement les effectifs, les publics cibles
et les quantités individuelles du menu.

Une denrée standard et ses variantes restent distinctes pendant la saisie. Au
moment de créer le mouvement de stock, leurs quantités sont converties puis
agrégées par denrée afin de respecter l’unicité d’une ligne de stock par
mouvement.

Depuis l’administration de la distribution, l’extraction des listes de courses
demande une date de début et une date de fin inclusives. L’archive ne contient
que les menus compris dans cette période et les unités dont la présence la
recoupe. Les lignes soumises à un régime sont incluses dans le PDF uniquement si
le compteur correspondant de l’unité est supérieur à zéro ; le libellé indique
le régime et le nombre de personnes concernées.

Pour une sortie publique, le serveur impose :

```text
séjour       = séjour correspondant au jeton
type         = SORTIE
origine      = DISTRIBUTION active du référentiel global
utilisateur  = utilisateur technique
groupe       = unité sélectionnée et appartenant au séjour
menu         = menu sélectionné et appartenant au séjour
```

Le serveur doit également vérifier l’activité des ressources, la disponibilité
du repas pour le séjour, l’appartenance de chaque denrée au menu, la validité des
quantités et le jeton CSRF.

## 12. Journal et mouvements de stock

Un mouvement représente un événement du journal de stock. Il référence un
séjour, l’utilisateur créateur, un type, une origine, éventuellement une unité et
un menu, une date, une référence documentaire et un commentaire.

Les types de mouvements constituent un référentiel global limité à :

* `ENTREE` ;
* `SORTIE`.

Il n’existe pas de type `AJUSTEMENT`. Une correction est enregistrée sous la
forme d’un nouveau mouvement d’entrée ou de sortie avec une origine adaptée,
notamment `CORRECTION` ou `INVENTAIRE`.

Les origines sont un référentiel global, disponible dans tous les séjours, et comprennent initialement :

* `FOURNISSEUR` ;
* `DISTRIBUTION` ;
* `INVENTAIRE` ;
* `POUBELLE` ;
* `RETOUR_ALIMENTAIRE` ;
* `CORRECTION`.

Chaque denrée doit appartenir au même séjour que le mouvement.
Les quantités des lignes sont positives ; leur effet sur le stock dépend du type
de mouvement. Une ligne peut détailler les conditionnements utilisés.

Les mouvements peuvent être modifiés ou annulés par un gestionnaire dans le
périmètre du séjour. Ils ne peuvent pas être supprimés. Un motif est obligatoire.
Une modification conserve l’état avant et après ; une annulation conserve le
mouvement mais retire son effet du calcul du stock. L’audit enregistre l’auteur,
la date et le motif. Toute opération reste protégée par CSRF et vérifie
l’appartenance au séjour côté serveur. Sur mobile, le glissement vers la gauche
propose l’annulation du mouvement.

## 13. Index et contraintes

Les tables et colonnes utilisent le `snake_case`. Les contraintes sont nommées
explicitement avec les préfixes `pk_`, `fk_`, `uq_` et `chk_`. Les index utilisent
le préfixe `idx_`.

Les noms d’index Doctrine et Liquibase doivent être identiques. Les relations
facultatives utilisent des index complets, sans clause PostgreSQL
`WHERE ... IS NOT NULL`, afin d’être reconnues correctement par Doctrine.

Les contraintes d’unicité de denrée et fournisseur portent également sur
`sejour_id` : ces valeurs sont uniques au sein d’un séjour. Le code d’une origine
de mouvement est, lui, unique globalement.

## 14. État fonctionnel

Sont fonctionnels dans le dépôt actuel :

* l’authentification et le contrôle des utilisateurs actifs ;
* la création d’utilisateurs et la réinitialisation des mots de passe par e-mail ;
* l’administration et la sélection multi-séjour ;
* la gestion des unités participantes et de leurs besoins alimentaires ;
* les dossiers des participants, leurs documents et leur registre de présence ;
* les situations particulières, leurs participants et leurs tâches ;
* la gestion des fournisseurs, denrées, références et conditionnements ;
* la gestion des menus, recettes, régimes alimentaires et publics cibles ;
* la distribution publique multi-séjour par jeton et QR code, sans écriture lors
  d’un GET et avec confirmation idempotente ;
* la saisie et la consultation des mouvements de stock ;
* les exports PDF, dont les listes de courses filtrées par période et unité ;
* l’anonymisation et la purge selon les délais de conservation ;
* la détection des références de documents manquantes et des fichiers
  orphelins ;
* les tests PHPUnit utilisant une base dédiée ;
* l’envoi d’e-mails applicatifs.

Après anonymisation d’un séjour, les unités participantes et les données
d’intendance — fournisseurs, denrées, menus, recettes et mouvements de stock —
sont conservées sans échéance automatique afin de préserver l’historique
opérationnel. Le séjour reste visible des administrateurs sous un statut
désactivé. Les données personnelles des participants, leurs documents, leurs
présences et les situations particulières restent soumises aux suppressions
automatiques décrites ci-dessus.

La validation du mapping Doctrine est fonctionnelle. Les types JSONB, les
expressions temporelles et la jointure ManyToMany multi-séjour utilisent les
déclarations compatibles avec les versions actuelles de Doctrine. La comparaison
du schéma continue néanmoins à afficher des écarts volontaires provenant des
defaults UUID et de certains index PostgreSQL définis par Liquibase ; sa sortie
reste strictement diagnostique.

## 15. Commandes principales

### Installation et Docker

```bash
make help
make install
make build
make rebuild
make up
make down
make restart
make ps
make logs
make shell
```

`make reset` supprime les conteneurs et la base locale. Cette commande est
réservée à une installation locale jetable.

### Symfony et assets

```bash
make console ARGS="about"
make cache-clear
make assets-compile
```

### Composer

```bash
make composer-install
make composer ARGS="require nom/package"
```

### Liquibase

```bash
make db-validate
make db-status
make db-status-dev
make db-sql
make db-sql-dev
make db-update
make db-update-dev
make db-history
make db-shell
```

### Doctrine et tests

```bash
make doctrine-validate
make db-check-connection
make test-db-reset
make test
```

`make test` recrée `campement_test`, applique Liquibase avec le contexte `dev`,
puis exécute PHPUnit. Aucune base contenant des données à conserver ne doit être
utilisée comme base de tests.

## 16. Livraison et exploitation

Les informations propres à une installation — hôtes, domaines, plages réseau,
utilisateurs nominatifs, chemins, sauvegardes et commandes de livraison — ne
doivent pas figurer dans un document suivi par Git.

Le dépôt ne documente que les invariants nécessaires au développement :

* les secrets et fichiers d’environnement restent hors Git ;
* les images amont PHP, Nginx, PostgreSQL, Liquibase, Composer et Trivy sont
  épinglées par digest ;
* les images finales PHP, Nginx, PostgreSQL, Liquibase et sauvegarde embarquent
  les artefacts livrés — code, dépendances, assets, scripts ou changelogs — sans
  bind mount du dépôt pour ces éléments ;
* les conteneurs de production utilisent un utilisateur non-root explicite, une
  racine en lecture seule, `cap_drop: ALL`, `no-new-privileges` et uniquement
  les volumes ou tmpfs nécessaires en écriture ;
* chaque service possède des plafonds CPU, mémoire et PID vérifiés par le smoke
  test, et Nginx n'est publié que sur `127.0.0.1` par défaut ;
* les hôtes et proxies de confiance sont configurés explicitement ;
* les rôles de lecture, d’écriture et de migration doivent être séparés ;
* une sauvegarde chiffrée et restaurable de la base et des documents précède
  toute migration de données ; la clé privée Age reste séparée du dépôt, de
  l'application et du stockage des sauvegardes ;
* une livraison est suivie de contrôles applicatifs, de migration, de journaux
  et de restauration ;
* aucune commande destructive ne doit cibler un environnement contenant des
  données à conserver.

La CI générale s'exécute sur `dev` et `main`, audite Composer, Importmap et npm,
puis construit et analyse avec Trivy les cinq images finales PHP, Nginx,
PostgreSQL, Liquibase et sauvegarde ; le scan PHP couvre aussi `vendor/`
réellement livré.
Pour chaque push et pull request visant `dev` ou `main`, un job indépendant
exécute `scripts/ci-production-smoke.sh` dans un projet Compose jetable : construction
des images finales, base vierge, migrations, transition des rôles PostgreSQL,
requête Doctrine avec le rôle applicatif, refus d'en-têtes `Host` et
`X-Forwarded-Host` hostiles,
sous-réseau HBA limité aux rôles attendus et refus effectif d'un rôle réseau
non autorisé,
sauvegarde chiffrée, déchiffrement et restauration effective de la base et d'un
document témoin, maintenance et vérification du durcissement et des limites de
ressources. Les secrets et clés éphémères générés par la CI sont masqués avant
leur export.

Dependabot surveille chaque semaine Composer, npm, GitHub Actions et les bases
Docker. Chaque commit de `main` construit une seule fois les cinq images et les
publie dans GHCR sous `sha-<commit>`, avec SBOM, provenance et attestation
GitHub signée via Sigstore. Un tag `v*` n'est publiable que si son commit
provient de `main` et si ces images candidates existent ; les étiquettes
sémantiques sont alors ajoutées aux mêmes digests, sans reconstruction.

La livraison reste manuelle et n'utilise aucun runner de production. Le fichier
local `.env.release` contient uniquement les cinq références GHCR immuables par
digest. La surcharge `compose.release.yaml` retire toutes les constructions
locales ; les commandes `make release-*` vérifient les attestations signées,
téléchargent les images, exécutent Liquibase et démarrent les services sans que
GitHub se connecte au serveur.

La logique applicative complexe n'est pas conservée dans les contrôleurs : les
formulaires participants, la présentation des menus, les invitations et
périmètres utilisateurs et l'enregistrement multi-lignes des stocks disposent
de services dédiés. Les limites de longueur doivent être vérifiées côté serveur
et, pour les invariants métier, dans les entités.

Le runbook opérationnel est maintenu localement dans
`.local/PRODUCTION_RUNBOOK.md` et l’architecture DAT/DIN/DEX dans
`.local/Campement_DAT_DIN_DEX.docx`. Ces documents décrivent l’hôte applicatif,
le reverse proxy, les règles `DOCKER-USER`, les sauvegardes et le retour arrière
de l’installation courante. Le répertoire `.local/` est ignoré par Git et ne
doit jamais être forcé dans un commit.

## 17. Règles de collaboration

Le dépôt suit désormais ce cycle de publication :

* `dev` est la branche d’intégration des évolutions ;
* `main` est la branche stable utilisée pour les livraisons ;
* une publication passe de `dev` vers `main` après validation de la suite de
  tests et reçoit un tag `vX.Y` sur le commit livré ;
* tout correctif réalisé sur `main` doit être reporté dans `dev` afin d’éviter
  une divergence durable.

* Présenter les changements structurants avant leur réalisation lorsqu’un choix
  métier ou technique reste nécessaire.
* Préserver les modifications locales et les données existantes.
* Liquibase reste l’unique source de vérité du schéma.
* Ne jamais exécuter une mise à jour automatique du schéma Doctrine.
* Ne jamais modifier un changeset versionné déjà appliqué sur une base durable.
* Conserver les conventions et composants visuels existants.
* Documenter les décisions structurantes.
* Privilégier une première version simple avant les optimisations.
* Ne pas introduire de dépendance sans justification et validation.
* Ne pas exposer directement les entités dans une API sans décision préalable.
* Éviter la logique métier complexe dans les contrôleurs ; l’extraire dans un
  service lorsqu’elle devient réutilisable ou difficile à tester.
* Vérifier systématiquement les droits et l’appartenance au séjour côté serveur.
* Après une modification frontale, recompiler et vérifier visuellement les assets.
* Après une évolution à risque, exécuter les validations et tests proportionnés.

## 18. Priorités recommandées

1. mettre en place une copie hors site versionnée ou immuable des sauvegardes
   chiffrées, la rotation des clés Age et un exercice périodique de restauration
   avec les clés réelles ;
2. superviser l'âge et le succès des sauvegardes en exploitation ;
3. maintenir le runbook local et le DAT/DIN/DEX après chaque changement
   d’infrastructure, puis versionner séparément la route Traefik après validation
   explicite de l’opérateur ;
4. augmenter la couverture fonctionnelle des parcours sensibles ;
5. poursuivre l’accessibilité de l’interface.

## 19. Décisions restant à préciser

Les points suivants restent à arbitrer :

* les droits détaillés de consultation du journal pour `ROLE_GROUPE` ;
* le gestionnaire de clés Age et le stockage hors site des sauvegardes ;
* la fréquence des tests de restauration des sauvegardes ;
* le niveau de couverture de tests attendu pour chaque module.
