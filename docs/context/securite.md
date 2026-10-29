# Sécurité — Campement

> Exigences de sécurité, menaces identifiées et contrôles **bloquants**, organisés selon l'OWASP Top 10 (édition 2025).
> Les contrôles sont exécutés avant chaque push quand c'est possible, et obligatoirement avant la production :
> un contrôle non coché, ou sans objet non justifié, interdit le push concerné ou la mise en production.
> Ce fichier décrit les règles, jamais les failles : les écarts connus vont uniquement dans `etat.md` §04.

## 01 Exigences (OWASP Top 10 : 2025)

### A01 — Contrôle d'accès

- Refus par défaut : toute route exige un rôle, sauf liste explicite de routes publiques.
- Contrôle côté serveur à chaque requête : rôle **et** appartenance de l'objet au périmètre de l'utilisateur.
- Aucune donnée ne passe d'un utilisateur, d'une unité ou d'un séjour à l'autre : un identifiant reçu
  n'est jamais cru sans vérification (protection IDOR).
- Identifiants exposés (URL, API, formulaires, liens) en **UUID** (v7 de préférence), jamais en entier
  auto-incrémenté ; l'UUID limite l'énumération mais ne remplace pas le contrôle d'accès.
- Champs modifiables définis par liste blanche (aucune assignation de masse de `role`, `owner`…).
- Liens publics (partage, QR code, webhook) protégés par un jeton aléatoire long, révocable, à durée limitée.
- Appels sortants vers une URL fournie par l'utilisateur interdits, ou limités à une liste d'hôtes autorisés (SSRF).
- Routes d'administration réservées et testées.

### A02 — Configuration

- Mode débogage, profileur et pages d'erreur détaillées désactivés hors développement.
- En-têtes de sécurité : CSP, HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors`.
- Aucun compte ni mot de passe par défaut ; services internes non exposés.
- Conteneurs sans privilège ; système de fichiers en lecture seule quand c'est possible.

### A03 — Chaîne d'approvisionnement logicielle

- Dépendances verrouillées (fichiers de verrou commités) ; audit sans vulnérabilité haute.
- Nouvelle dépendance seulement après vérification de sa maintenance, de sa licence et de ses CVE.
- Images de base à jour, épinglées par digest ; actions GitHub épinglées par SHA ; Dependabot actif.

### A04 — Cryptographie

- TLS partout ; mots de passe hachés par l'algorithme recommandé du framework (Argon2id / bcrypt).
- Jetons générés par un générateur cryptographique ; sauvegardes chiffrées, clé privée hors du serveur.
- Aucun algorithme maison ni secret codé en dur.

### A05 — Injection

- Requêtes paramétrées uniquement ; échappement automatique des gabarits ; aucune commande système construite
  à partir d'une entrée.
- Validation serveur de toutes les entrées (type, longueur, format).

### A06 — Conception

- Confidentialité et cloisonnement pris en compte dès la conception (`confidentialite.md`).
- Limitation de débit sur les points d'entrée publics et les traitements coûteux (exports, PDF, recherche).
- Téléversements : type et taille contrôlés, stockage hors web, nom régénéré.
- Webhooks et captures idempotents.

### A07 — Authentification

- Limitation des tentatives de connexion et de réinitialisation ; message identique que le compte existe ou non.
- Jetons de réinitialisation à usage unique, expirants, purgés après expiration.
- Cookies de session `HttpOnly`, `Secure`, `SameSite` ; rotation de session à la connexion ; jeton CSRF sur les formulaires.
- Double authentification pour les comptes administrateurs exposés sur Internet.

### A08 — Intégrité des logiciels et des données

- Images signées et consommées par digest ; releases produites par la CI uniquement.
- Signature des webhooks vérifiée ; aucune désérialisation de données non fiables.
- Migrations Liquibase seules ; un changeset appliqué est immuable.

### A09 — Journalisation et alertes

- Journaliser les échecs de connexion, les refus d'accès et les actions d'administration, sans donnée personnelle ni secret.
- Alerte en cas de série d'échecs ou d'erreurs serveur ; journaux conservés selon `confidentialite.md`.

### A10 — Conditions exceptionnelles

- Toute erreur est interceptée : message générique à l'utilisateur, détail uniquement dans le journal.
- Échec fermé : une erreur pendant un contrôle d'accès ou une validation refuse l'action.
- Délais d'expiration sur les appels externes ; transaction annulée en cas d'erreur partielle.

### Secrets

- Aucun secret dans le dépôt ni dans son historique, vérifié avant chaque push.
- Variables de production stockées dans un fichier dédié par environnement, hors Git, à accès restreint.
- `.env.example` à jour, sans valeur réelle.

## 02 Modèle de menaces

| Menace | OWASP | Vecteur | Impact | Mesure |
|---|---|---|---|---|
| Accès aux données d'un autre utilisateur | A01 | Identifiant modifié dans l'URL ou le formulaire | Critique | Contrôle d'accès par objet + test de cloisonnement |
| Énumération d'objets | A01 | Identifiants séquentiels devinables (`/commande/42` → `/43`) | Élevé | UUID + contrôle d'accès |
| Élévation de privilèges | A01 | Champ `role` ou `owner` ajouté au formulaire (assignation de masse) | Critique | Liste blanche des champs modifiables |
| Lien public deviné | A01 | Lien de partage, QR code ou webhook prévisible | Élevé | Jeton aléatoire long, révocable, à durée limitée |
| Requête interne forcée (SSRF) | A01 | URL fournie par l'utilisateur | Élevé | Liste d'hôtes autorisés |
| Configuration exposée | A02 | Mode débogage, page d'erreur détaillée | Élevé | Configuration de production contrôlée |
| Chaîne d'approvisionnement | A03 | Action GitHub ou paquet compromis | Critique | Épinglage par SHA, verrous, audit |
| Dépendance vulnérable | A03 | Bibliothèque ou image obsolète | Élevé | Dependabot, Trivy, audit |
| Fuite de données | A04 | Sauvegarde ou export non chiffré | Critique | Chiffrement, accès restreint, purge |
| Injection SQL / XSS | A05 | Entrée non validée | Élevé | Requêtes paramétrées, échappement, CSP |
| Fichier malveillant | A06 | Téléversement de document | Élevé | Type et taille contrôlés, stockage hors web, nom régénéré |
| Saturation | A06 | Export, PDF ou recherche coûteux répétés | Moyen | Limitation de débit, pagination, tâches de fond |
| Vol de compte | A07 | Bourrage d'identifiants | Élevé | Limitation des tentatives, double authentification admin |
| Énumération de comptes | A07 | Messages différents à la connexion ou à la réinitialisation | Moyen | Message identique |
| Action à l'insu de l'utilisateur | A07 | Requête forgée depuis un autre site (CSRF) | Élevé | Jeton CSRF, cookies `SameSite` |
| Rejeu | A08 | Webhook ou capture renvoyé plusieurs fois | Moyen | Signature vérifiée, idempotence |
| Attaque non détectée | A09 | Absence de journal ou d'alerte | Élevé | Journalisation des échecs et alertes |
| Contournement par erreur | A10 | Exception pendant un contrôle | Élevé | Échec fermé |
| Fuite de secret | — | Commit, journal, message d'erreur | Critique | Gitleaks, journaux sans secret, erreurs génériques |
| Données résiduelles | — | Donnée purgée encore présente en sauvegarde ou export | Moyen | Rétention des sauvegardes bornée, exports éphémères |
| Accès aux données d'un autre séjour | A01 | Identifiant d'un objet d'un autre séjour envoyé par le navigateur | Critique | `ContexteSejour`, `PerimetreUtilisateur`, appartenance vérifiée côté serveur |
| Fuite de données de santé de mineurs | A01 | Fiche sanitaire, vaccins ou situation particulière consultés hors périmètre | Critique | Module administratif réservé, contrôle d'accès au téléchargement, anonymisation 48 h après le séjour |
| Fichier malveillant dans un dossier | A06 | Document participant piégé | Élevé | PDF, JPEG ou PNG uniquement, 10 Mo au plus, nom régénéré, stockage hors web |
| Saisie publique abusive | A06 | Soumissions répétées sur le lien de distribution | Moyen | Limitation à 30 soumissions par minute, clé de soumission idempotente |

## 03 Surfaces d'attaque

| Point d'entrée | Public | Protection |
|---|---|---|
| `/login`, `/mot-de-passe-oublie`, `/reinitialiser-mot-de-passe/…` | Oui | Limitation : 5 connexions / 15 min, 3 demandes et 10 saisies de réinitialisation / 15 min |
| `/distribution/{jeton}`, `/sortie-consommation` | Oui | Jeton propre au séjour, fermé après le dernier jour ; 30 soumissions / min |
| `/conditions-utilisation`, `/politique-confidentialite` | Oui | Pages statiques |
| Toutes les autres routes | Non | `IS_AUTHENTICATED_FULLY` + rôle + séjour courant + périmètre |

## 04 Contrôles bloquants

> Avant chaque push quand c'est possible (lignes marquées *push*), et tous avant la production.
> « Sans objet » doit être justifié sur la même ligne.

### Contrôle d'accès (A01)

- [ ] *push* — Toutes les routes non publiques exigent une authentification.
- [ ] *push* — Tests de cloisonnement verts : un utilisateur ne lit ni ne modifie l'objet d'un autre.
- [ ] *push* — Aucun nouvel identifiant exposé en entier auto-incrémenté.
- [ ] *push* — Routes d'administration inaccessibles aux autres rôles.

### Configuration et intégrité (A02, A08)

- [ ] Mode débogage désactivé et en-têtes de sécurité présents en recette.
- [ ] Image signée, consommée par digest.

### Dépendances (A03)

- [ ] *push* — Audit des dépendances sans vulnérabilité haute ; toute nouvelle dépendance justifiée dans la PR.
- [ ] CI verte sur le commit livré, y compris Trivy.

### Entrées et erreurs (A05, A10)

- [ ] *push* — Nouvelles entrées validées côté serveur ; aucune requête construite par concaténation.
- [ ] *push* — Erreurs testées : message générique, échec fermé.

### Authentification et journalisation (A07, A09)

- [ ] *push* — Formulaires protégés par CSRF ; limitation des tentatives en place.
- [ ] Échecs de connexion et refus d'accès visibles dans les journaux de recette.

### Secrets

- [ ] *push* — Aucun secret détecté dans le diff ni dans l'historique (Gitleaks / Betterleaks).
- [ ] *push* — `.env.example` à jour, sans valeur réelle.
- [ ] Variables de production présentes dans leur fichier dédié, hors Git.

### Données et confidentialité

- [ ] *push* — Migrations Liquibase validées.
- [ ] *push* — Purges de `confidentialite.md` implémentées et testées pour toute nouvelle donnée personnelle.
- [ ] *push* — Aucune donnée personnelle dans les journaux.
- [ ] Sauvegarde restaurable avant migration de production.

### Recette

- [ ] Parcours principaux vérifiés en recette ; accessibilité contrôlée si l'interface change.
- [ ] Promotion en production explicitement autorisée.
