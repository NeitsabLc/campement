# Journal des changements

Le projet repart sur une histoire autonome à compter de la séparation avec
Scout Market. Les versions antérieures à cette base sont conservées dans
l’ancien historique du dépôt et ne constituent pas une chaîne de migration.

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
