# Journal des changements

Le projet repart sur une histoire autonome à compter de la séparation avec
Scout Market. Les versions antérieures à cette base sont conservées dans
l’ancien historique du dépôt et ne constituent pas une chaîne de migration.

## Non publié

### Documentation

- alignement du README et du contexte projet sur la CI visant `main`, la
  publication des images limitée aux releases, le déploiement de la recette par
  `homelab-deploy` et l’expéditeur `no-reply@neitsab.net`.

## [1.0.3](https://github.com/NeitsabLc/campement/compare/v1.0.2...v1.0.3) (2026-09-07)


### Corrections

* **auth:** forcer le rechargement apres authentification ([156f29c](https://github.com/NeitsabLc/campement/commit/156f29cce8671efde19a019887ea583d5b885ba3))
* **auth:** forcer le rechargement après authentification ([960b5c4](https://github.com/NeitsabLc/campement/commit/960b5c4721ac66480a984e9982e779fdd3567dfa))
* **build:** actualiser le paquet age Alpine ([0397593](https://github.com/NeitsabLc/campement/commit/03975930afe40314efd0aa2aa9249202782be3c0))
* **build:** aligner la version age de l'environnement ([45d5234](https://github.com/NeitsabLc/campement/commit/45d5234773beafc8b1b265236782f4129b3ca0a4))
* **release:** permettre la reprise d'une promotion ([3fed58a](https://github.com/NeitsabLc/campement/commit/3fed58a8795fecac46524c2dda66280014f07103))

## [1.0.2](https://github.com/NeitsabLc/campement/compare/v1.0.1...v1.0.2) (2026-09-05)


### Corrections

* **release:** automatiser la creation des tags ([f7b6509](https://github.com/NeitsabLc/campement/commit/f7b65094bddae0a5ccfe414d660c4d6778ce4f3c))
* **release:** automatiser la creation des tags ([1174124](https://github.com/NeitsabLc/campement/commit/11741242615c141001d21ef428a9ef7e4d6ad2d1))
* **release:** promouvoir les releases publiees ([22765a8](https://github.com/NeitsabLc/campement/commit/22765a87743dedb71abdcfea04660bd881d463f8))

## [1.0.1](https://github.com/NeitsabLc/campement/compare/v1.0.0...v1.0.1) (2026-09-05)


### Corrections

* **deps-dev:** bump friendsofphp/php-cs-fixer from 3.95.18 to 3.95.24 in /app ([8b524d0](https://github.com/NeitsabLc/campement/commit/8b524d0194447ceacbf00f5ccc32af09f013bd8c))
* **deps-dev:** bump friendsofphp/php-cs-fixer in /app ([70c6a8b](https://github.com/NeitsabLc/campement/commit/70c6a8bdbf362ff40449228526a0c567e5d2672a))
* **deps:** bump the symfony group across 1 directory with 12 updates ([a0c841e](https://github.com/NeitsabLc/campement/commit/a0c841e63333400885c8e31e2feb54aeb2cf289f))
* **deps:** bump the symfony group across 1 directory with 12 updates ([31992b7](https://github.com/NeitsabLc/campement/commit/31992b7879d68dc9c680cae6694b185104760e7e))

## [1.0.0] - 2026-09-02

### Base applicative

- reprise du code stable de Campement 1.4.1 ;
- réinitialisation de l’historique Git ;
- remplacement de l’ancienne chaîne Liquibase par un schéma initial autonome
  `V001` et un jeu de démonstration `D001` ;
- maintien des modules propres à Campement sans modification de la production
  existante.

### Modifié

- reprise du design de la page Menus de Scout Market : navigation par date,
  édition des quatre repas d’une journée sur une seule page et vue dédiée aux
  repas Explo et pique-niques ;
- suppression complète de la notion de seuil minimum de stock dans les
  denrées, l'interface, la duplication de séjour et le schéma initial.

### Intendance

- maintien des denrées, fournisseurs, références, conditionnements, stocks et
  recettes ;
- maintien d’une seule grille de menus par séjour ;
- prise en compte des régimes végétarien, sans lactose et sans gluten ;
- configuration par unité des repas Explo, pique-nique 1, pique-nique 2 et non
  pris ;
- distribution Scout Market classique calculée selon la présence des unités et
  leurs configurations de repas ;
- commande simplifiée à trois bornes : premier repas à déduire du stock,
  premier repas commandé et dernier repas commandé.

### Validation

- ajout de tests unitaires du calcul de distribution et du calcul de commande ;
- validation du schéma initial et des fixtures sur une base PostgreSQL vierge.
