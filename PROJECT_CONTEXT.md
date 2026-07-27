# Campement — Contexte du projet

## 1. Objectif et état du projet

Campement est une application web de gestion des camps accompagnés. Elle est
destinée principalement aux équipes pilotes et permet également aux unités
participantes de saisir leurs distributions de denrées.

L’application couvre actuellement :

* l’authentification et la gestion des mots de passe ;
* l’administration des utilisateurs, rôles et accès ;
* la création, l’activation et la sélection de plusieurs séjours ;
* la gestion des unités participantes ;
* la gestion des menus et des quantités individuelles par public cible ;
* la gestion des denrées, fournisseurs, références et conditionnements ;
* la saisie et la consultation des mouvements de stock ;
* la distribution publique sécurisée par un lien propre à chaque séjour ;
* l’envoi d’e-mails de création de compte et de réinitialisation de mot de passe.

Le projet est fonctionnel et déployé en production. Les nouvelles évolutions
doivent donc préserver les données existantes et la compatibilité avec le schéma
déjà appliqué.

## 2. Organisation du dépôt

```text
campement/
├── compose.yaml
├── Makefile
├── PROJECT_CONTEXT.md
├── database/
│   └── changelog/
│       ├── db.changelog-master.yaml
│       ├── versioned/        # changements communs, y compris en production
│       │   ├── V001__initialisation.sql
│       │   ├── V002__create-table-campement.sql
│       │   └── V003...V007
│       └── dev/              # données réservées au développement et aux tests
│           └── D001...D003
└── app/
    ├── assets/               # JavaScript, Stimulus, CSS, images et polices
    ├── bin/
    ├── config/
    ├── public/
    ├── src/
    │   ├── Controller/
    │   ├── Doctrine/
    │   ├── Entity/
    │   ├── Repository/
    │   ├── Security/
    │   ├── Service/
    │   └── Twig/
    ├── templates/
    ├── tests/
    └── composer.json
```

Le code Symfony se trouve dans `app/`.

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

Après toute modification de CSS, JavaScript, image, police ou layout :

1. exécuter `make assets-compile` ;
2. vérifier que `debug:asset-map` contient la ressource modifiée ;
3. vérifier que le HTML rendu charge bien la feuille attendue ;
4. vérifier que l’URL générée répond avec un statut HTTP 200 ;
5. contrôler une règle propre à la page modifiée, car une ancienne feuille peut
   répondre en 200 sans contenir les nouvelles règles.

Vider le cache Symfony ne recompile pas `app/public/assets`. Nginx sert
directement les ressources déjà compilées dans ce répertoire.

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

L’application étant en production :

* un changeset versionné déjà appliqué ne doit plus être modifié ;
* toute nouvelle évolution utilise un nouveau fichier `Vxxx` ;
* les données de démonstration ou de test restent dans `database/changelog/dev/` ;
* une sauvegarde PostgreSQL doit précéder toute mise à jour de production ;
* `make reset` et la suppression des volumes sont réservés aux environnements
  locaux jetables et ne doivent jamais être utilisés en production.

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

Toutes les clés primaires métier utilisent un UUID version 7 généré par
PostgreSQL :

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
* les unités participantes et leurs effectifs.

### Intendance

Pour le séjour sélectionné, le module Intendance permet de gérer :

* les fournisseurs ;
* les denrées et leurs unités de référence ;
* les références fournisseur et leurs conditionnements ;
* les menus et les quantités individuelles par public ;
* les entrées, sorties et corrections de stock ;
* la consultation des mouvements ;
* le lien et le QR code de distribution publique.

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
un effectif jeune, un effectif adulte, un commentaire éventuel et un statut
actif.

Les effectifs décrivent l’unité mais ne sont pas utilisés automatiquement pour
calculer les quantités du menu. Ils sont indépendants des publics cibles.

### Publics cibles

`public_cible` est le référentiel global des publics utilisés pour définir les
portions individuelles :

* `LOUVETEAUX_JEANNETTES` — Louveteaux-Jeannettes ;
* `SCOUTS_GUIDES` — Scouts-Guides ;
* `PIONNIERS_CARAVELLES` — Pionniers-Caravelles ;
* `ADULTE` — Adulte.

`sejour_public_cible` sélectionne les publics utilisés par chaque séjour et porte
leur ordre ainsi que leur statut actif pour ce séjour.

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

`menu_denree` associe une denrée à un menu et porte son ordre d’affichage. Une
denrée ne peut apparaître qu’une fois dans un même menu.

`menu_denree_quantite` porte une quantité individuelle exprimée dans l’unité de
référence de la denrée pour un public cible donné. Une seule quantité peut être
définie pour le couple `menu_denree + sejour_public_cible`.

Ces quantités sont informatives. L’application ne calcule pas automatiquement
une quantité totale à partir des effectifs de l’unité.

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
le jeton. Toute régénération invalide immédiatement l’ancien lien.

Le séjour est toujours déduit du jeton. Il ne dépend ni de la session d’un
utilisateur connecté ni d’un identifiant de séjour envoyé librement par le
navigateur. Les soumissions POST publiques sont soumises à une limitation de
fréquence.

Le parcours est le suivant :

1. sélection de l’unité participante ;
2. sélection du jour ;
3. sélection du repas ;
4. chargement du menu correspondant ;
5. affichage de ses denrées et quantités individuelles ;
6. saisie de la quantité totale réellement prise pour chaque denrée ;
7. validation définitive du mouvement.

L’application ne combine pas automatiquement les effectifs, les publics cibles
et les quantités individuelles du menu.

Pour une sortie publique, le serveur impose :

```text
séjour       = séjour correspondant au jeton
type         = SORTIE
origine      = DISTRIBUTION active de ce séjour
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

Les origines sont configurées par séjour et comprennent initialement :

* `FOURNISSEUR` ;
* `DISTRIBUTION` ;
* `INVENTAIRE` ;
* `POUBELLE` ;
* `RETOUR_ALIMENTAIRE` ;
* `CORRECTION`.

L’origine et chaque denrée doivent appartenir au même séjour que le mouvement.
Les quantités des lignes sont positives ; leur effet sur le stock dépend du type
de mouvement. Une ligne peut détailler les conditionnements utilisés.

Après validation, un mouvement n’est ni modifiable ni supprimable. Toute
correction produit un nouveau mouvement afin de préserver la traçabilité.

## 13. Index et contraintes

Les tables et colonnes utilisent le `snake_case`. Les contraintes sont nommées
explicitement avec les préfixes `pk_`, `fk_`, `uq_` et `chk_`. Les index utilisent
le préfixe `idx_`.

Les noms d’index Doctrine et Liquibase doivent être identiques. Les relations
facultatives utilisent des index complets, sans clause PostgreSQL
`WHERE ... IS NOT NULL`, afin d’être reconnues correctement par Doctrine.

Les contraintes d’unicité de denrée, fournisseur et origine portent également
sur `sejour_id` : ces valeurs sont uniques au sein d’un séjour et non globalement.

## 14. État fonctionnel

Sont fonctionnels dans le dépôt actuel :

* l’authentification et le contrôle des utilisateurs actifs ;
* la création d’utilisateurs et la réinitialisation des mots de passe par e-mail ;
* l’administration et la sélection multi-séjour ;
* la gestion des unités participantes ;
* la gestion des fournisseurs, denrées, références et conditionnements ;
* la gestion des menus et publics cibles ;
* la distribution publique multi-séjour par jeton et QR code ;
* la saisie et la consultation des mouvements de stock ;
* les tests PHPUnit utilisant une base dédiée ;
* l’envoi SMTP en production.

La validation du mapping Doctrine est fonctionnelle. La comparaison du schéma
peut continuer à proposer uniquement les suppressions volontaires des defaults
UUID PostgreSQL.

Une dépréciation Doctrine connue concerne l’attribut `nullable` de la jointure
ManyToMany `Utilisateur::$sejoursGeres`. Elle n’est pas bloquante actuellement,
mais doit être corrigée avant Doctrine ORM 4.

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
destructive et strictement interdite en production.

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
puis exécute PHPUnit. La base de développement et la base de production ne
doivent jamais être utilisées comme base de tests.

## 16. Production

La production actuelle repose sur :

* une VM Ubuntu hébergée sur un NAS QNAP ;
* le dépôt installé dans `/srv/campement` ;
* Docker Compose pour PostgreSQL, PHP et Nginx ;
* un reverse proxy HTTPS hébergé sur une VM distincte ;
* l’URL publique `https://campement.neitsab.net` ;
* Symfony en environnement `prod`, avec le debug désactivé ;
* un serveur SMTP OVH, avec envoi synchrone ;
* des sauvegardes PostgreSQL placées dans `/srv/backups/campement`.

La branche actuellement déployée est `dev`. Cette information décrit l’état
actuel et ne constitue pas une recommandation à long terme.

Les secrets de production sont conservés dans `/srv/campement/app/.env.local` et
ne doivent jamais être copiés dans le dépôt, les journaux, ce document ou une
conversation. Sont notamment sensibles `APP_SECRET`, `DATABASE_URL`, le DSN SMTP
et tout mot de passe.

Avant une mise à jour de production :

1. vérifier que le dépôt de production est propre ;
2. effectuer et contrôler une sauvegarde PostgreSQL ;
3. récupérer uniquement les commits attendus ;
4. appliquer les nouveaux changesets Liquibase sans contexte `dev` ;
5. installer les dépendances et compiler les assets si nécessaire ;
6. vider le cache de production et redémarrer les services concernés ;
7. vérifier les services, les journaux et les parcours applicatifs touchés.

## 17. Règles de collaboration

* Présenter les changements structurants avant leur réalisation lorsqu’un choix
  métier ou technique reste nécessaire.
* Préserver les modifications locales et les données existantes.
* Liquibase reste l’unique source de vérité du schéma.
* Ne jamais exécuter une mise à jour automatique du schéma Doctrine.
* Ne jamais modifier un changeset versionné déjà appliqué en production.
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

1. supprimer ou restreindre à `127.0.0.1` l’exposition PostgreSQL de production
   actuellement observée sur le port `5433` ;
2. documenter et fiabiliser le déploiement, la sauvegarde et le retour arrière ;
3. corriger la dépréciation Doctrine sur `Utilisateur::$sejoursGeres` ;
4. augmenter la couverture fonctionnelle des parcours sensibles ;
5. renforcer la cohérence transactionnelle des mouvements de stock ;
6. décider et implémenter la prévention des doubles distributions ;
7. poursuivre l’ergonomie mobile et l’accessibilité ;
8. définir une stratégie de branches et de livraison adaptée à la production.

## 19. Décisions restant à préciser

Les points suivants restent à arbitrer :

* la prévention d’une double saisie pour une unité et un menu ;
* les droits détaillés de consultation du journal pour `ROLE_GROUPE` ;
* le comportement exact lorsqu’aucun menu n’existe pour le jour et le repas ;
* la politique d’archivage et de conservation des séjours ;
* la rotation périodique ou uniquement manuelle des jetons publics ;
* la fréquence, la rétention et la vérification des sauvegardes ;
* la procédure formalisée de déploiement et de retour arrière ;
* la branche de production et le processus de livraison ;
* le niveau de couverture de tests attendu pour chaque module.
