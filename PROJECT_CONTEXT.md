# Campement — Contexte du projet

## 1. Objectif

Campement est une application de gestion des séjours, groupes, menus, denrées, fournisseurs et mouvements de stock.

Le projet est développé sous la forme d’un POC. L’objectif est de valider les principaux parcours métier autour :

* de la saisie des menus ;
* de la saisie des sorties de stock liées aux consommations ;
* de la saisie des entrées et ajustements de stock ;
* de la consultation du journal de stock.

Les autres données nécessaires au POC sont saisies directement dans PostgreSQL au moyen de requêtes SQL.

## 2. Organisation du dépôt

```text
campement/
├── compose.yaml
├── Makefile
├── database/
│   └── changelog/
│       ├── db.changelog-master.yaml
│       └── versioned/
│           ├── V001__initialisation.sql
│           └── V002__create-table-campement.sql
└── app/
    ├── bin/
    ├── config/
    ├── public/
    ├── src/
    │   ├── Entity/
    │   └── Repository/
    ├── templates/
    ├── tests/
    └── composer.json
```

Le code Symfony se trouve dans `app/`.

## 3. Stack technique

* PHP 8.4 ;
* Symfony 8.1 ;
* Doctrine ORM pour le mapping objet-relationnel ;
* PostgreSQL 18 ;
* Liquibase ;
* Docker Compose ;
* Nginx ;
* UUID version 7 ;
* Twig pour les premiers écrans.

### Messages de confirmation

Tous les messages de confirmation positifs doivent disparaître automatiquement
3 secondes après leur affichage. Utiliser la classe globale `flash--success` ;
le comportement est géré dans `app/assets/app.js` et fonctionne aussi après une
navigation Turbo.

Les messages d’erreur et d’avertissement ne disparaissent pas automatiquement,
afin que l’utilisateur puisse les lire et corriger le formulaire.

### Chargement obligatoire des styles

La feuille de styles principale doit toujours être chargée explicitement dans
`app/templates/base.html.twig` :

```twig
{% block stylesheets %}
    <link rel="stylesheet" href="{{ asset('styles/app.css') }}">
{% endblock %}
```

Ne pas charger `styles/app.css` uniquement avec un `import` depuis
`app/assets/app.js`. Ce chargement indirect dépend de la résolution des
dépendances et du cache d’AssetMapper et a déjà produit des pages sans style.

Après toute modification du layout ou des assets, vérifier les trois points
suivants :

1. exécuter impérativement `make assets-compile` : Nginx sert directement les
   fichiers déjà présents dans `app/public/assets`, qui deviennent sinon
   obsolètes même après un `cache:clear` ;
2. `php bin/console debug:asset-map` contient `styles/app.css` ;
3. le HTML rendu contient une balise `<link rel="stylesheet">` vers cette ressource ;
4. l’URL générée pour la feuille CSS répond avec un statut HTTP 200 ;
5. contrôler dans le navigateur une règle propre à la page modifiée, pas
   uniquement les styles généraux : une ancienne feuille peut répondre en 200
   tout en ne contenant pas les nouvelles règles.

Vider le cache Symfony ne recompile pas `app/public/assets`. La commande
`asset-map:compile` est donc obligatoire dans la configuration Docker/Nginx de
ce projet après chaque changement de CSS, JavaScript, image ou police.

## 4. Responsabilité des outils

### Liquibase

Liquibase constitue l’unique source de vérité du schéma PostgreSQL.

Toutes les créations et évolutions de tables, colonnes, contraintes, index et données initiales passent par les changesets Liquibase.

Le projet n’étant pas encore en production, les changements de structure peuvent être intégrés directement dans `V002__create-table-campement.sql`, puis appliqués après suppression et recréation de la base.

### Doctrine

Doctrine est utilisé uniquement pour :

* mapper les tables vers les entités ;
* gérer les associations ;
* utiliser les repositories ;
* valider le mapping ;
* construire et exécuter les requêtes applicatives.

Doctrine ne doit jamais modifier le schéma.

Les commandes suivantes ne doivent jamais être exécutées :

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

## 5. Gestion des identifiants

Toutes les clés primaires métier utilisent un UUID version 7 généré par PostgreSQL :

```sql
id UUID NOT NULL DEFAULT uuidv7()
```

Le default PostgreSQL reste déclaré uniquement dans Liquibase.

Le mapping Doctrine déclare seulement l’identifiant :

```php
#[ORM\Id]
#[ORM\Column(type: 'uuid')]
private Uuid $id;
```

Il est normal que la commande suivante propose de supprimer les defaults UUID :

```bash
make console ARGS="doctrine:schema:update --dump-sql"
```

Exemple d’écart volontaire :

```sql
ALTER TABLE ... ALTER id DROP DEFAULT;
```

Ces commandes ne doivent jamais être exécutées.

## 6. Stratégie du POC

### Écrans prévus

Le périmètre fonctionnel du POC est limité aux écrans suivants :

1. saisie des menus par un gestionnaire ;
2. saisie publique des sorties de consommation par les groupes ;
3. saisie des entrées de stock par un gestionnaire ;
4. saisie des ajustements de stock par un gestionnaire ;
5. consultation minimale des mouvements de stock.

### Données administrées directement en SQL

Les éléments suivants sont saisis directement en base pour le moment :

* les séjours ;
* les groupes ;
* les utilisateurs et leurs rôles ;
* les types de repas disponibles pour le séjour ;
* les unités ;
* les denrées ;
* les fournisseurs ;
* les références fournisseur ;
* les conditionnements ;
* les référentiels de mouvements.

Aucun écran CRUD générique n’est prévu pour ces éléments dans le POC.

## 7. Utilisateurs, rôles et accès

Les rôles métier retenus sont :

* `ROLE_GESTIONNAIRE` ;
* `ROLE_GROUPE`;
* `ROLE_ADMIN`.

Un utilisateur peut être associé à zéro ou un groupe au moyen de `utilisateur.groupe_id`.

La relation est nullable :

* un gestionnaire n’est normalement associé à aucun groupe ;
* un utilisateur de groupe peut être associé à un groupe ;
* plusieurs utilisateurs peuvent être associés au même groupe.

L’association utilisateur–groupe est administrée directement en base de données pour le POC.

### Saisie publique des sorties

L’écran de saisie des sorties de consommation est accessible sans connexion.

Il contient un sélecteur de groupe parmi les groupes actifs du séjour.

Les mouvements créés depuis cet écran sont associés à l’utilisateur technique :

```text
saisie-consommation@campement.local
```

Cet utilisateur :

* n’est pas destiné à l’authentification interactive ;
* ne possède aucun rôle métier ;
* permet de conserver `mouvement_stock.utilisateur_id` obligatoire ;
* identifie les mouvements issus du formulaire public.

Les écrans de gestion restent destinés au rôle `ROLE_GESTIONNAIRE`.

## 8. Séjour utilisé dans le POC

Le POC utilise un seul séjour actif.

Il n’existe donc pas d’écran de sélection du séjour ni de séjour actif stocké en session.

L’application doit rechercher l’unique séjour actif.

Si aucun séjour actif ou plusieurs séjours actifs sont trouvés, l’application doit afficher une erreur explicite et ne pas sélectionner arbitrairement un séjour.

## 9. Modèle métier

### Entités principales

Le modèle comprend notamment :

* `Sejour` ;
* `TypeRepas` ;
* `SejourTypeRepas` ;
* `Groupe` ;
* `Menu` ;
* `MenuDenree` ;
* `MenuDenreeQuantite` ;
* `PublicCible` ;
* `SejourPublicCible` ;
* `Unite` ;
* `Denree` ;
* `Fournisseur` ;
* `ReferenceFournisseur` ;
* `ReferenceFournisseurConditionnement` ;
* `TypeMouvement` ;
* `OrigineMouvement` ;
* `MouvementStock` ;
* `MouvementStockLigne` ;
* `MouvementStockLigneConditionnement` ;
* `Utilisateur`.

Les entités `PublicCible`, `SejourPublicCible`, `MenuDenree` et `MenuDenreeQuantite` disposent de leurs repositories Doctrine dédiés.

### Séjours et types de repas

```text
sejour
  └── sejour_type_repas
        └── type_repas
```

`sejour_type_repas` configure les types de repas disponibles pour chaque séjour.

Un menu référence :

* un séjour ;
* un `sejour_type_repas` ;
* une date ;
* éventuellement un nom ;
* éventuellement un commentaire ;
* un statut actif.

Un menu ne référence pas directement `type_repas`.

### Groupes

Un groupe appartient à un séjour.

Il possède notamment :

* un nom ;
* un effectif jeune ;
* un effectif adulte ;
* un commentaire éventuel ;
* un statut actif.

Les effectifs jeune et adulte décrivent le groupe, mais ne sont pas utilisés automatiquement pour calculer les quantités du menu.

La notion d’effectif d’un groupe est indépendante des catégories de consommation.

### Publics cibles

`public_cible` est le référentiel global et figé des publics utilisés pour définir les portions individuelles des denrées d’un menu.

Les catégories initiales sont :

* `LOUVETEAUX_JEANNETTES` — Louveteaux-Jeannettes ;
* `SCOUTS_GUIDES` — Scouts-Guides ;
* `PIONNIERS_CARAVELLES` — Pionniers-Caravelles ;
* `ADULTE` — Adulte.

Chaque public possède :

* un code ;
* un libellé ;
* un ordre ;
* un statut actif.

`sejour_public_cible` sélectionne les publics utilisés par chaque séjour et porte leur ordre ainsi que leur statut actif pour ce séjour.

### Composition des menus

```text
menu
  └── menu_denree
        └── menu_denree_quantite
              └── sejour_public_cible
                    └── public_cible
```

`menu_denree` associe une denrée à un menu et permet de définir son ordre d’affichage.

Une denrée ne peut apparaître qu’une seule fois dans un même menu.

`menu_denree_quantite` porte une quantité individuelle pour un public cible donné.

Une seule quantité individuelle peut être définie pour un couple :

```text
menu_denree + sejour_public_cible
```

La quantité individuelle est exprimée dans l’unité de référence de la denrée.

Exemple :

```text
Pâtes — unité de référence : gramme

Louveteaux-Jeannettes : 100 g
Scouts-Guides         : 120 g
Pionniers-Caravelles  : 150 g
Adulte                : 150 g
```

Ces quantités sont informatives. L’application ne calcule pas automatiquement une quantité totale à partir des effectifs du groupe.

### Denrées et unités

Une denrée possède une unité de référence.

Exemples d’unités initiales :

* gramme ;
* kilogramme ;
* litre ;
* millilitre ;
* pièce.

Les quantités individuelles du menu et les quantités des lignes de mouvement sont exprimées dans l’unité de référence de la denrée.

### Fournisseurs

Une référence fournisseur relie :

* un fournisseur ;
* une denrée ;
* une référence commerciale ;
* une désignation.

Une référence fournisseur peut posséder plusieurs conditionnements.

Chaque conditionnement indique :

* un ordre ;
* un libellé ;
* une quantité contenue ;
* une unité de contenu.

## 10. Saisie des menus

L’écran de saisie des menus est réservé au gestionnaire.

Il permet de définir, pour un jour et un type de repas actif sur le séjour :

* les informations générales du menu ;
* la liste ordonnée des denrées ;
* les quantités individuelles par catégorie de consommation.

Le gestionnaire ne peut choisir qu’un `sejour_type_repas` appartenant au séjour actif.

## 11. Saisie publique des sorties de consommation

Le parcours public est le suivant :

1. sélection du groupe ;
2. sélection du jour ;
3. sélection du repas ;
4. chargement du menu correspondant ;
5. affichage des denrées du menu ;
6. affichage des quantités individuelles par catégorie de consommation ;
7. saisie de la quantité totale réellement prise pour chaque denrée ;
8. validation définitive du mouvement.

Le groupe réalise lui-même le calcul de la quantité nécessaire, en dehors de l’application.

L’application ne combine pas automatiquement :

* les effectifs du groupe ;
* les catégories de consommation ;
* les quantités individuelles du menu.

### Données imposées par le serveur

Pour une sortie publique, l’application fixe automatiquement :

```text
séjour       = séjour actif unique
type         = SORTIE
origine      = DISTRIBUTION
utilisateur  = utilisateur technique
groupe       = groupe sélectionné
menu         = menu sélectionné
```

Le navigateur ne doit pas pouvoir choisir librement le type de mouvement, l’origine ou l’utilisateur.

### Contrôles obligatoires

Le serveur doit vérifier que :

* le groupe est actif ;
* le groupe appartient au séjour actif ;
* le menu est actif ;
* le menu appartient au séjour actif ;
* le type de repas du menu est disponible pour le séjour ;
* chaque denrée soumise appartient réellement au menu ;
* les quantités totales saisies sont strictement positives ;
* le formulaire est protégé contre les attaques CSRF.

## 12. Journal de stock

Un mouvement de stock représente un événement du journal de stock.

Il référence :

* un séjour ;
* l’utilisateur ayant créé le mouvement ;
* un type de mouvement ;
* une origine ;
* éventuellement un groupe ;
* éventuellement un menu ;
* une date ;
* une référence documentaire éventuelle ;
* un commentaire éventuel.

Un mouvement contient plusieurs lignes.

Une ligne référence :

* le mouvement ;
* une denrée ;
* éventuellement une référence fournisseur ;
* une quantité exprimée dans l’unité de référence.

Les quantités des lignes restent positives. Leur effet sur le stock dépend du type de mouvement.

Une ligne peut contenir le détail des conditionnements utilisés.

### Immutabilité

Après validation, un mouvement de stock n’est ni modifiable ni supprimable depuis l’application.

Une correction doit être enregistrée sous la forme d’un nouveau mouvement d’ajustement.

## 13. Consultation des mouvements

Un écran minimal de consultation en lecture seule fait partie du POC.

La liste doit présenter au minimum :

* la date et l’heure ;
* le type ;
* l’origine ;
* le groupe éventuel ;
* le menu éventuel ;
* l’utilisateur créateur ;
* le nombre de lignes.

Le détail doit afficher les lignes et leurs quantités.

Le gestionnaire peut consulter tous les mouvements du séjour actif.

Les droits de consultation associés à `ROLE_GROUPE` restent à préciser, l’écran public de sortie ne nécessitant pas d’authentification.

## 14. Types initiaux

### Types de repas

* `PETIT_DEJEUNER` ;
* `DEJEUNER` ;
* `GOUTER` ;
* `DINER`.

### Catégories de consommation

* `LOUVETEAUX_JEANNETTES` ;
* `SCOUTS_GUIDES` ;
* `PIONNIERS_CARAVELLES` ;
* `ADULTE`.

### Types de mouvements

* `ENTREE` ;
* `SORTIE` ;

### Origines de mouvements

* `FOURNISSEUR` ;
* `DISTRIBUTION` ;
* `INVENTAIRE` ;
* `POUBELLE` ;
* `RETOUR_ALIMENTAIRE` ;
* `CORRECTION`.

## 15. Index et contraintes

Les tables et colonnes utilisent le `snake_case`.

Les contraintes sont nommées explicitement avec les préfixes :

* `pk_` ;
* `fk_` ;
* `uq_` ;
* `chk_`.

Les index sont nommés explicitement avec le préfixe `idx_`.

Les index Doctrine et Liquibase doivent porter les mêmes noms.

Les relations ajoutées utilisent notamment :

```text
idx_utilisateur_groupe
idx_menu_denree_menu
idx_menu_denree_denree
idx_menu_denree_quantite_menu_denree
idx_menu_denree_quantite_sejour_public_cible
```

Les contraintes uniques principales comprennent notamment :

```text
uq_utilisateur_email
uq_menu_sejour_date_type
uq_menu_denree
uq_menu_denree_quantite
uq_public_cible_code
uq_sejour_public_cible
```

Les index des relations facultatives restent des index complets, sans condition PostgreSQL `WHERE ... IS NOT NULL`, afin d’être reconnus correctement par Doctrine.

## 16. État de synchronisation

Le schéma Liquibase contient désormais :

* `public_cible` ;
* `sejour_public_cible` ;
* `menu_denree` ;
* `menu_denree_quantite` ;
* l’association nullable `utilisateur.groupe_id` ;
* l’utilisateur technique de saisie publique.

Les entités et repositories Doctrine correspondants ont été ajoutés.

Les relations Doctrine existantes ont été conservées unidirectionnelles lorsqu’aucun côté inverse n’existait, afin d’éviter des associations bidirectionnelles incomplètes.

La validation du mapping Doctrine est fonctionnelle.

La commande de comparaison du schéma peut continuer à proposer uniquement les suppressions volontaires des defaults UUID PostgreSQL.

## 17. Commandes principales

### Docker

```bash
make help
make build
make up
make down
make restart
make reset
make ps
make logs
make shell
```

### Symfony

```bash
make console ARGS="about"
make cache-clear
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

### Doctrine

```bash
make doctrine-validate
make db-check-connection
make console ARGS="doctrine:schema:update --dump-sql"
```

Après une modification directe du changeset initial :

```bash
docker compose down --volumes
make up
make db-update
make db-validate
make doctrine-validate
```

## 18. Règles de collaboration

* Ne rien ajouter au projet sans validation préalable.
* Présenter les changements fichier par fichier.
* Liquibase reste l’unique source de vérité du schéma.
* Ne jamais exécuter une mise à jour automatique du schéma Doctrine.
* Conserver les conventions existantes.
* Documenter les décisions structurantes.
* Privilégier une première version simple avant les optimisations.
* Ne pas introduire de dépendance sans justification et validation.
* Ne pas exposer directement les entités dans une API sans décision préalable.
* Ne pas ajouter de logique métier complexe dans les contrôleurs.

## 19. Ordre de réalisation recommandé

1. finaliser l’authentification des gestionnaires avec l’entité `Utilisateur` ;
2. mettre en place la résolution de l’unique séjour actif ;
3. développer la saisie des menus et de leurs quantités individuelles ;
4. développer la saisie publique des sorties de consommation ;
5. développer la saisie des entrées ;
6. développer la saisie des ajustements ;
7. développer la consultation en lecture seule du journal de stock ;
8. ajouter les tests fonctionnels essentiels.

## 20. Décisions restant à préciser

Les points suivants restent à arbitrer au moment de développer les écrans concernés :

* la page d’accueil ;
* la navigation principale ;
* la charte visuelle minimale ;
* la méthode de sélection des denrées dans la saisie d’un menu ;
* l’obligation ou non de renseigner les quatre catégories pour chaque denrée ;
* le comportement lorsqu’aucun menu n’existe pour le jour et le repas sélectionnés ;
* la prévention d’une double saisie de consommation pour un même groupe et un même menu ;
* les droits de consultation éventuels de `ROLE_GROUPE` ;
* la stratégie précise de tests fonctionnels.
