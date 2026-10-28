# Décisions — Campement

> Choix durables et leçons apprises. Chaque entrée précise son motif ;
> une nouvelle décision remplace explicitement l'ancienne au lieu de s'y ajouter.
> Dates : premier commit qui introduit ou formalise la décision.

## 01 Décisions

| Date | Décision | Motif |
|---|---|---|
| 2026-09-02 | Liquibase possède le schéma ; Doctrine ne sert qu'au mapping et aux requêtes | Une seule chaîne de migration, relisible en SQL |
| 2026-09-02 | Un changeset appliqué est immuable ; sauvegarde avant toute migration durable | Rejouabilité et retour arrière |
| 2026-09-02 | Bases de test dédiées et recréables ; une base métier n'est jamais une cible de test destructive | Protéger les données réelles |
| 2026-09-02 | `ContexteSejour` et `PerimetreUtilisateur` centralisent le séjour courant et les habilitations | Un seul point de contrôle du cloisonnement |
| 2026-09-02 | Anonymisation des données personnelles 48 h après la fin du séjour | Données de mineurs et de santé : conservation minimale |
| 2026-09-28 | GitHub est la forge de référence ; `CONTRIBUTING.md` définit le cycle vers `main` | Un seul flux de revue |
| 2026-09-28 | Images signées et consommées par digest | Livraison traçable |
| 2026-09-30 | Release Please pilote les versions | Versions et changelog sans intervention manuelle |
| 2026-10-22 | Contrôleurs fins ; règles métier et contrôles d'accès côté serveur | Testabilité, sécurité |
| 2026-10-22 | Les détails privés de production appartiennent au projet Infra Homelab | Dépôt public |
| 2026-10-28 | Code, tables et colonnes en français, par exception à la règle commune (code en anglais) | Vocabulaire métier SGDF entièrement français |

## 02 Leçons apprises

> Chaque bug qui a coûté du temps devient une règle.

| Ce qui a cassé | Correction | Règle retenue |
|---|---|---|
| Une panne SMTP bloquait l'anonymisation des séjours (#45) | Anonymisation indépendante de l'envoi des e-mails | Aucune purge ni anonymisation ne dépend d'une notification |
| La suppression d'un compte supprimait aussi ses mouvements de stock (#53) | Réattribution à un compte d'historique avant suppression | Supprimer une personne ne détruit jamais la traçabilité métier : on anonymise la référence |
| Purges et sauvegardes lancées comme services permanents (#26, #29) | Exécution en tâche ponctuelle | Toute tâche planifiée est ponctuelle et rend un code de sortie contrôlé |
| Le changelog n'était plus généré (#46) | Configuration de Release Please restaurée | La PR de version est relue avant fusion : changelog et version présents |
| Image PHP vulnérable (CVE PHP 8.4, #71) | Image épinglée par digest, refus des versions vulnérables en CI | La CI refuse une image PHP antérieure à la version corrigée |
