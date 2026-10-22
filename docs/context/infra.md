# Livraison et exploitation — Campement

## Développement et livraison partagés

- GitHub Actions contrôle qualité, tests, migrations et images ; consulter les workflows présents sur la branche de travail.
- Conventional Commits et Release Please pilotent les versions ; ne pas fabriquer de tag de release manuellement.
- Les images de livraison sont publiées dans GHCR, signées et consommées par digest.
- La production exige une sauvegarde restaurable avant migration et une promotion explicitement autorisée.
- Ne pas appliquer les données de développement sur une recette persistante ou en production.
- La présence d’une release ne prouve pas son déploiement : vérifier les manifestes et l’état réel.

## Informations propres à l’installation

Les machines, domaines, accès, digests actifs, sauvegardes et procédures distantes sont décrits
dans la documentation d’exploitation privée, hors de ce dépôt, de même que les dossiers DAT/DIN/DEX.
Ces informations ne doivent pas être recopiées dans ce dépôt public.

## Secrets

Valeurs locales dans les fichiers d’environnement ignorés et répertoires de secrets prévus par les procédures.
La CI utilise ses secrets GitHub ; les clés privées de déchiffrement restent séparées des serveurs et archives.
Documenter uniquement les noms de variables et emplacements, jamais leurs valeurs.
