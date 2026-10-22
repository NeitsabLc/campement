# Métier — Campement

Référence issue du contexte existant, à maintenir avec le code. Les détails de livraison historiques ont été retirés.

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

Pour le goûter, le sélecteur de recettes propose les catégories `GOUTER` et
`DESSERT`. Les quantités d’une denrée ajoutée hors recette sont initialement
vides. À chaque changement de repas, le calendrier place le jour actif en haut
de sa zone de défilement. Sur le tableau de bord, le résumé affiche une fois
chaque instance de recette et regroupe les denrées hors recette sous la mention
« En supplément ».

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
