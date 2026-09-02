# Journal des changements

Le projet repart sur une histoire autonome à compter de la séparation avec
Scout Market. Les versions antérieures à cette base sont conservées dans
l’ancien historique du dépôt et ne constituent pas une chaîne de migration.

## [Non publié]

### Modifié

- reprise du design de la page Menus de Scout Market : navigation par date,
  édition des quatre repas d’une journée sur une seule page et vue dédiée aux
  repas Explo et pique-niques ;
- suppression complète de la notion de seuil minimum de stock dans les
  denrées, l’interface, la duplication de séjour et le schéma initial.

## [1.0.0] - 2026-09-02

### Base applicative

- reprise du code stable de Campement 1.4.1 ;
- réinitialisation de l’historique Git ;
- remplacement de l’ancienne chaîne Liquibase par un schéma initial autonome
  `V001` et un jeu de démonstration `D001` ;
- maintien des modules propres à Campement sans modification de la production
  existante.

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
