# Confidentialité — Campement

> Données personnelles traitées, utilité, durée de conservation et mesures de protection.
> Base de l'analyse d'impact (PIA) au sens du RGPD : Campement traite des données de mineurs et de santé.

## 01 Règles communes

- **Confidentialité dès la conception** : aucune donnée personnelle n'est ajoutée sans utilité écrite ici,
  durée de conservation et mécanisme de suppression, dès le premier développement.
- **Minimisation** : collecter le strict nécessaire ; préférer un nombre ou un agrégat à une donnée nominative.
- **Suppression dès la fin de l'usage** : chaque donnée a une purge automatique, testée, déclenchée
  au plus tôt (fin de séjour, désactivation, expiration) ; l'anonymisation remplace la suppression
  seulement quand une trace est nécessaire.
- **Cloisonnement** : une donnée n'est visible que dans le périmètre de son propriétaire ;
  tout accès est vérifié côté serveur et couvert par un test.
- Aucune donnée personnelle dans les journaux, les dépôts, les tickets ou les jeux de test.

## 02 Finalité

Organiser les camps accompagnés : gestion des participants et de leurs dossiers, suivi des présences
et des situations particulières, intendance. Les besoins alimentaires des unités sont des nombres, sans lien nominatif.

## 03 Données personnelles

| Données | Personnes | Utilité | Conservation | Purge |
|---|---|---|---|---|
| Nom, prénom, date de naissance, téléphone, e-mail | Participants (jeunes et adultes) | Dossier du séjour | Jusqu'à 48 h après la fin du séjour | `app:sejours:anonymiser` |
| Téléphone et e-mail des parents, contact d'urgence | Responsables légaux, contacts | Joindre la famille | Jusqu'à 48 h après la fin du séjour | `app:sejours:anonymiser` |
| Qualifications, diplômes, stage BAFA | Participants adultes | Encadrement réglementaire | Jusqu'à 48 h après la fin du séjour | `app:sejours:anonymiser` |
| Fiche sanitaire, vaccins (données de santé) | Participants | Sécurité sanitaire du camp | Jusqu'à 48 h après la fin du séjour | `app:sejours:anonymiser` (fichiers supprimés) |
| Autorisation de départ en camp, justificatifs de qualification | Participants | Obligations du séjour | Jusqu'à 48 h après la fin du séjour | `app:sejours:anonymiser` (fichiers supprimés) |
| Présences journalières | Participants | Suivi de l'effectif | Jusqu'à 48 h après la fin du séjour | `app:sejours:anonymiser` |
| Situations particulières (accident, événement grave, signalement) | Participants concernés | Suivi des déclarations | 14 jours après la fin du séjour | `app:donnees:purger` |
| Nom, prénom, e-mail, empreinte du mot de passe | Utilisateurs | Compte et connexion | Un mois après la désactivation | `app:donnees:purger` |
| Jeton de réinitialisation | Utilisateurs | Changement de mot de passe | Jusqu'à son expiration, usage unique | Effacé à l'usage |
| Auteur d'un mouvement de stock | Utilisateurs | Traçabilité | Réattribué à un compte d'historique à la suppression du compte | `app:donnees:purger` |

## 04 Données non personnelles

- Unités participantes : nom, effectifs, **nombres** de régimes, période de présence ; conservées avec l'intendance.
- Séjours, menus, denrées, stocks et mouvements : conservés tant qu'ils sont utiles.

## 05 Mesures de protection

- Anonymisation, purge et réconciliation des documents orphelins lancées par le service `maintenance`
  (`docker/php/maintenance.sh`) ; l'anonymisation ne dépend pas de l'envoi d'e-mails.
- Accès limité au séjour courant et au périmètre de l'utilisateur (`ContexteSejour`, `PerimetreUtilisateur`).
- Documents stockés hors de l'espace web, sous un nom aléatoire, inclus dans les sauvegardes.
- Mots de passe hachés ; rôles PostgreSQL séparés ; sauvegardes chiffrées (base et documents), clé privée hors du serveur,
  supprimées après sept jours (`docker/postgres/backup.sh`).
- Aucune donnée réelle dans le dépôt : jeux de données fictifs uniquement.

## 06 Droits des personnes

| Droit | Comment l'exercer |
|---|---|
| Accès, rectification | Auprès du gestionnaire du séjour |
| Effacement | Suppression par le gestionnaire ; automatique 48 h après la fin du séjour |
| Contact | Aucun responsable de traitement désigné à ce jour (§07) |

## 07 Points à surveiller

- Désigner le responsable de traitement et le contact pour l'exercice des droits.
- Les sauvegardes des sept derniers jours contiennent encore les données d'un séjour anonymisé : limite connue et acceptée.
- Vérifier la planification effective de l'anonymisation, des purges et de la réconciliation des documents.
