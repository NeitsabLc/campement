# Journal des changements

Ce fichier suit les évolutions fonctionnelles, techniques et de sécurité du
projet. Le format s’inspire de *Keep a Changelog* et les versions publiées
suivent le versionnement sémantique.

## [1.4.0] - 2026-08-21

### Ajouté

- gestion des variantes alimentaires `Végétarien`, `Sans lactose` et
  `Sans gluten` sur les lignes de recette et de menu, avec reprise des données
  de la recette lors de l’ajout ou de la resynchronisation dans un menu ;
- saisie, sur chaque unité participante lorsque l’intendance est active, du
  nombre de personnes concernées par chaque besoin alimentaire ;
- filtrage des variantes sur la distribution publique et dans les listes de
  courses PDF selon les besoins de l’unité sélectionnée ;
- choix d’une période lors de l’extraction des fiches de recette, afin de ne
  générer que les repas et unités présents sur cette période ;
- bouton permettant d’enregistrer un menu puis d’ouvrir directement le repas
  suivant ;
- affichage ou masquage du mot de passe sur la page de connexion ;
- tri des listes de recettes et de denrées, avec pictogrammes associant barres
  et flèche ;
- suite Playwright E2E couvrant l’authentification, les restrictions par rôle,
  l’isolation multi-séjour et les CRUD des unités, fournisseurs, denrées,
  recettes, menus, situations particulières et présences, exécutée dans la CI ;
- fixture Liquibase de navigation avec un séjour actif, ses trois modules
  activés et un participant utilisable par le registre de présence.

### Modifié

- ajout des denrées en bas des formulaires de recette et de mouvement de stock ;
- les listes déroulantes recherchées valident leur résultat avec Entrée sans
  soumettre le formulaire parent et utilisent une graisse typographique
  homogène ;
- la duplication des quantités de menu tient compte du type de repas et de la
  catégorie de la ligne ;
- les pages légales sont recentrées et la formulation relative à la rotation
  des journaux a été clarifiée ;
- documentation locale DAT/DIN/DEX et runbook d’exploitation alignés sur la
  migration de la production vers un hôte applicatif dédié derrière Traefik,
  avec filtrage `DOCKER-USER`, sauvegardes chiffrées et procédure de retour
  arrière ;
- les comptes groupe sans séjour accessible arrivent sur un accueil explicite,
  sans liens dans le menu métier, et sont invités à contacter leurs responsables ;
- les lignes du registre de présence utilisent un lien unique au lieu de cibles
  interactives imbriquées ;
- le contrôle d’accessibilité vérifie que chaque URL attendue n’a pas été
  remplacée silencieusement par une redirection.

### Corrigé

- restauration du fonctionnement des sous-menus après un retour avec la
  navigation du navigateur ;
- rendu des lignes et affichage des commandes de tri sur la page des denrées ;
- téléchargement effectif des fiches de recette après validation de la période.

## [1.3.0] - 2026-08-12

### Ajouté

- smoke test indépendant de la surcharge Compose de production sur chaque push
  et pull request visant `dev` ou `main`, avec base vierge, migrations, rôles PostgreSQL,
  sauvegarde, maintenance et contrôles HTTP ;
- audits des dépendances Importmap et npm dans la CI ;
- suivi hebdomadaire des dépendances Composer, npm, GitHub Actions et Docker par
  Dependabot ;
- publication sur les tags issus de `main` des cinq images GHCR avec SBOM,
  provenance et attestation GitHub signée via Sigstore ;
- publication préalable des images candidates sous `sha-<commit>` sur `main`,
  puis promotion des mêmes digests lors du tag sans reconstruction ;
- surcharge Compose et commandes manuelles de livraison par digest, avec
  vérification des attestations GitHub signées avant téléchargement ;
- sauvegarde Age de la base PostgreSQL et des documents, avec restauration réelle
  dans le smoke test de production ;
- commande locale `make backup-restore-test` pour vérifier le chiffrement et la
  restauration de la base et des documents ;
- commandes de qualité et d'exploitation alignées avec Bénévole Jambville :
  `make analyse-statique`, `make style`, `make style-fix`,
  `make test-accessibility` et `make maintenance-now`.

### Modifié

- construction multi-stage des images PHP et Nginx de production, avec code,
  dépendances sans outils de développement et assets compilés intégrés à
  l'image au lieu d'un bind mount du dépôt ;
- scénario d'initialisation de la base vierge aligné sur la migration historique
  V001, avant préparation et validation des rôles PostgreSQL limités ;
- passage à Liquibase 5.0.3 avec pilote PostgreSQL 42.7.12 vérifié par somme de
  contrôle et retrait du gestionnaire de paquets inutilisé de l'image finale ;
- extraction dans des services dédiés de la logique de formulaire participant,
  de présentation des menus, d'invitation et de périmètre utilisateurs et de
  l'enregistrement multi-lignes des stocks ;
- contrôle automatique du style PHP avec PHP-CS-Fixer.

### Sécurité

- mise à jour d'EasyAdmin vers `5.5.1` afin de corriger le contournement du
  contrôle d'accès par le répartiteur d'actions personnalisées ;
- épinglage par digest des images PHP, Nginx, PostgreSQL et Liquibase ;
- exécution non-root et en lecture seule des services de production, avec
  suppression des capabilities et `no-new-privileges` ;
- analyse Trivy des images PHP, Nginx, PostgreSQL et Liquibase, y compris leurs
  artefacts finaux réellement livrés ;
- mise à niveau des paquets Alpine de l'image Nginx pendant sa construction
  afin d'intégrer les correctifs de sécurité publiés ;
- validation silencieuse de Compose, masquage des secrets éphémères de CI et
  publication locale de Nginx sur `127.0.0.1` par défaut ;
- plafonds CPU, mémoire et PID sur tous les services de production, contrôlés
  sur les conteneurs créés par le smoke test ;
- limites serveur de 1 000 caractères pour les adresses fournisseur et de 2 000
  caractères pour les commentaires de tâche ;
- authentification SCRAM obligatoire pour les connexions PostgreSQL locales et
  réseau, y compris le rôle de contrôle de santé ;
- vérification des règles HBA propres à chaque rôle et refus effectif d'un rôle
  réseau valide mais non autorisé ;
- refus d'en-têtes `Host` et `X-Forwarded-Host` hostiles vérifié par le smoke test ;
- chiffrement Age des sauvegardes avant leur écriture sur le stockage, sans
  conservation d'un dump ou d'une archive documentaire en clair ;
- analyse Trivy et publication avec attestations de la cinquième image dédiée
  aux sauvegardes.

## [1.2.2] - 2026-08-12

### Supprimé

- l’action de suppression définitive des mouvements de stock ; une erreur de
  saisie doit désormais être traitée par l’annulation non destructive, y compris
  depuis l’action de glissement sur mobile.

### Sécurité

- l’analyse statique PHPStan, l’audit des dépendances Composer et le contrôle
  Trivy des vulnérabilités élevées ou critiques de l’image PHP sont exécutés
  dans la CI ;
- l’image PHP de base est épinglée par digest afin de maîtriser les mises à jour
  utilisées lors des reconstructions.

## [1.2.1] - 2026-08-11

### Corrigé

- chaque service de production utilise explicitement le login et le secret
  PostgreSQL correspondant à son rôle ; la CI valide également cette
  configuration avant livraison.

## [1.2.0] - 2026-08-11

### Ajouté

- audit des modifications, annulations et suppressions de mouvements de stock,
  avec conservation de l’état avant et après, de l’auteur, de la date et du
  motif ;
- annulation non destructive d’un mouvement, qui conserve son historique tout
  en retirant son effet du calcul du stock.

### Modifié

- la page publique distingue désormais l’absence de menu configuré de l’absence
  d’unité présente le jour de la distribution.
- la politique de conservation précise que les unités et les données
  d’intendance sont conservées sans échéance automatique pour préserver
  l’historique opérationnel.
- le jeton de distribution publique est renouvelé automatiquement lors de la
  réactivation d’un séjour et le lien est fermé après son dernier jour.

## [1.1.3] - 2026-08-10

### Sécurité

- les journaux d’accès Nginx masquent les jetons de distribution et de
  réinitialisation, suppriment les paramètres d’URL et n’enregistrent plus le
  référent, le User-Agent ou l’adresse du visiteur ;
- un processeur Monolog masque les secrets, identifiants, cookies, adresses et
  jetons présents dans les messages et contextes applicatifs ;
- la rotation des journaux Docker de production est limitée en taille et en
  nombre de fichiers.

## [1.1.2] - 2026-08-10

### Corrigé

- le pied de page affiche désormais la version applicative `1.1`.

## [1.1.1] - 2026-08-10

### Corrigé

- le contrôle de santé PostgreSQL peut utiliser le rôle interne de récupération
  après la désactivation du rôle historique, sans générer d’échecs de connexion
  répétés dans les journaux ;
- le rôle de sauvegarde peut lire les tables de suivi Liquibase du schéma
  `public`, y compris celles créées ultérieurement par le migrateur.

## [1.1.0] - 2026-08-10

### Ajouté

- réconciliation entre les documents référencés en base et les fichiers du
  volume, avec simulation par défaut et délai de sécurité avant suppression ;
- préparation d’une séparation des rôles PostgreSQL entre application,
  migrations et sauvegardes ;
- tests fonctionnels de l’idempotence de la distribution et de la conservation
  des affectations multi-séjours ;
- configuration explicite des hôtes et proxies de confiance.
- exemple PostgreSQL HBA générique, la politique réseau réelle étant conservée
  dans un fichier local ignoré par Git.

### Modifié

- création des menus nécessaires à la fusion goûter-déjeuner déplacée vers les
  parcours authentifiés ;
- documentation publique recentrée sur l’installation locale ;
- informations opérationnelles de livraison déplacées dans un runbook local
  non versionné ;
- valeur pseudo-secrète de l’environnement de développement remplacée par un
  libellé explicitement non sensible ;
- sous-réseau Docker et chemin du fichier HBA rendus configurables ;
- mappings Doctrine JSONB, valeurs temporelles et jointure multi-séjour alignés
  avec la version actuelle de Doctrine ;
- adoption de `main` comme branche stable de livraison et conservation de
  `dev` comme branche d’intégration des développements.

### Sécurité

- les consultations du lien public de distribution n’écrivent plus en base ;
- une clé de soumission unique empêche l’enregistrement multiple d’une même
  confirmation publique ;
- un gestionnaire ne peut plus modifier indirectement les affectations d’un
  autre séjour ni désactiver globalement un gestionnaire multi-séjours ;
- la procédure de durcissement PostgreSQL conserve une phase de validation avant
  de désactiver la connexion du rôle historique.

## [1.0.0] - 2026-08-09

### Ajouté

- gestion multi-séjour et administration des utilisateurs ;
- modules d’intendance, de participants et de situations particulières ;
- gestion des documents, présences et exports PDF ;
- menus, recettes, fournisseurs, conditionnements et mouvements de stock ;
- distribution publique par jeton et QR code ;
- invitations et réinitialisation des mots de passe par e-mail ;
- politique de conservation et anonymisation des données ;
- pages publiques de conditions d’utilisation et de politique de
  confidentialité ;
- suite de tests fonctionnels et unitaires avec base dédiée.

### Architecture

- application Symfony 8.1 sur PHP 8.4 ;
- PostgreSQL 18, schéma géré exclusivement par Liquibase ;
- exécution locale avec Docker Compose et Nginx ;
- interface Twig, AssetMapper, Turbo et Stimulus.
