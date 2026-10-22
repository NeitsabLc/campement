# Décisions — Campement

- Liquibase possède le schéma ; Doctrine conserve le mapping et les requêtes.
- Un changeset appliqué est immuable. Sauvegarder avant toute migration sur des données durables.
- Les bases de test sont dédiées et recréables ; une base métier n’est jamais une cible de test destructive.
- Les contrôleurs restent fins ; les règles métier et les contrôles d’accès vivent côté serveur.
- GitHub est la forge de référence ; `CONTRIBUTING.md` définit le cycle courant vers `main`.
- Release Please et les images signées par digest permettent une livraison traçable.
- Les détails privés de production appartiennent au projet Infra Homelab.

Toute nouvelle décision doit préciser son motif et remplacer explicitement un ancien choix si nécessaire.
