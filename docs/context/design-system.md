# Design system — Campement

> Charte graphique Scouts et Guides de France (SGDF), commune à Campement, Bénévoles Jambville et Scout Market.
> Référence : charte graphique SGDF « identité visuelle » (couleurs p. 18, typographies p. 19-21, logo p. 8-10).
> Source des valeurs : `app/assets/styles/app.css` (`:root`). Toute nouvelle valeur passe d'abord par une variable.
> Les écarts propres à l'application sont listés en §10.

## 01 Identité et logo

| Fichier | Usage |
|---|---|
| `app/assets/images/logo-sgdf-horizontal.png` | En-têtes, page de connexion, écrans de distribution |
| `app/assets/images/logo-sgdf-vertical.png` | Espaces étroits, mise en page verticale |
| `app/assets/images/favicon.svg` | Icône d'onglet |

- Logo en bleu institutionnel sur fond blanc, en blanc sur fond bleu institutionnel.
- Zone de respiration vierge autour du logo : aucun texte, visuel ni partie de photo.
- Respecter la taille minimale de la charte (1,5 cm et 3,2 cm selon la version) ; à l'écran, ne jamais réduire
  le logo au point de rendre le texte illisible.
- Interdits : couleur non autorisée, déformation, modification, suppression d'un élément ou du symbole.
- Toujours avec un texte alternatif (`alt="Scouts et Guides de France"`).

## 02 Couleurs

### Couleurs de la charte

| Variable | Valeur | Rôle dans la charte | Contraste sur blanc |
|---|---|---|---|
| `--sgdf-blue` | `#003a5d` | **Couleur principale** (bleu institutionnel), présente sur tous les supports | 12 : 1 |
| `--sgdf-blue-light` | `#0077b3` | Scouts et guides | 4,9 : 1 |
| `--sgdf-green` | `#007254` | Compagnons | 5,9 : 1 |
| `--sgdf-violet` | `#6e74aa` | Vent du large | 4,6 : 1 |
| `--sgdf-red` | `#d03f15` | Pionniers et caravelles | 5,2 : 1 |
| `--sgdf-mint` | `#65bc99` | Farfadets | 2,3 : 1 |
| `--sgdf-orange` | `#ff8300` | Louveteaux et jeannettes | 2,4 : 1 |

- Les couleurs secondaires accompagnent le bleu institutionnel « en touches » : jamais à sa place.
- Aucune autre teinte de marque : une couleur hors de ce tableau est interdite (logo compris).

### Couleurs d'interface

| Variable | Valeur | Usage |
|---|---|---|
| `--surface` | `#ffffff` | Fond des cartes et formulaires |
| `--surface-muted` | `#f4f7f9` | Fond de page, survol des lignes |
| `--border` | `#d9e2e7` | Bordures, séparateurs |
| `--text` | `#003a5d` | Texte courant (bleu institutionnel, exigé par la charte sur fond clair) |
| `--text-muted` | `#526875` | Texte secondaire |
| `--link` | `#0077b3` | Liens, éléments actifs |
| `--success` | `#007254` | Succès |
| `--danger` | `#d03f15` (rouge de la charte) | Erreurs, actions destructives |
| `--danger-surface` | `#fef3f2` | Fond des messages d'erreur |
| `--focus` | `#0077b3` | Contour de focus clavier |

- Contraste minimal 4.5:1 pour le texte (3:1 pour les grands titres, les icônes et le contour de focus).
- `#65bc99` et `#ff8300` n'atteignent pas 3:1 sur blanc : décor et aplats uniquement, jamais pour du texte,
  une icône seule ou le focus.
- Ne jamais transmettre une information par la couleur seule (ajouter texte ou icône).
- Aucune couleur codée en dur : remplacer celles qui existent quand on touche le bloc.

## 03 Typographie

| Rôle (charte) | Police | Variable | Repli |
|---|---|---|---|
| Texte courant, formulaires | Sarabun (400, 700, italique) | `--font-body` | "Segoe UI", Arial, sans-serif |
| Titres et sous-titres, navigation, boutons | Raleway (400, 700) | `--font-title` | "Segoe UI", Arial, sans-serif |

- Sarabun en bleu institutionnel sur fond clair, en blanc sur fond foncé (règle de la charte).
- Caveat Brush, prévue par la charte pour le titrage, n'est pas utilisée dans les applications.
- Polices auto-hébergées dans `app/assets/fonts/` : aucun appel à un service externe.
- E-mails : polices système (`Arial, Helvetica, sans-serif`), jamais chargées depuis un service externe.
- Tailles en `rem` ; texte courant ≥ 1rem, mentions et métadonnées ≥ 0.75rem (la charte interdit moins de 6 pt).

## 04 Espacements et rayons

| Élément | Valeur |
|---|---|
| Rayon des champs et boutons | `0.3rem` |
| Rayon des cartes | `0.45rem` |
| Pastilles | `50%` |
| Ombre | `--shadow`, réservée aux éléments flottants (dialogues, menus) |
| Espacements | Échelle `0.25 / 0.5 / 1 / 1.5 / 2 / 3rem`, aucune autre valeur |

## 05 Icônes

- SVG en ligne, `fill`/`stroke` à `currentColor`, taille alignée sur le texte (1em à 1.25rem).
- Icône décorative : `aria-hidden="true"` ; bouton composé d'une seule icône : `aria-label` obligatoire.
- Réutiliser une icône existante avant d'en dessiner une ; style linéaire, trait régulier.

## 06 Composants

| Élément | Règle |
|---|---|
| Boutons | `.button`, `.button--primary` (bleu SGDF), `.button--danger-outline` ; un seul bouton primaire par zone |
| Focus | `outline: 3px solid var(--focus)` + `outline-offset: 2px`, jamais supprimé |
| Tableaux | `components/list_table.html.twig` et `sort_indicator.html.twig` pour les listes triables |

## 07 Messages et retours utilisateur

| Type | Affichage | Durée |
|---|---|---|
| Succès, information | Pop-up en haut à droite (`role="status"`) | Disparaît après **3 s** |
| Erreur générale | Pop-up en haut à droite (`role="alert"`) | Disparaît après **3 s** |
| Erreur de champ | Sous le champ, liée par `aria-describedby`, champ en `aria-invalid="true"` | Reste jusqu'à correction |

- Bouton de fermeture, pause du minuteur au survol et au focus, empilement de haut en bas.
- Messages courts, en français, orientés action (« Saisissez une date de fin postérieure au début »).

## 08 Accessibilité

- [ ] Cible WCAG 2.2 AA / RGAA, contrôlée par Axe.
- [ ] Chaque champ a un `<label>` visible ; les erreurs sont annoncées.
- [ ] Navigation complète au clavier, ordre logique, focus visible.
- [ ] Zones cliquables d'au moins 24 × 24 px (44 px recommandé sur mobile).

## 09 Points de rupture

| Nom | Largeur | Comportement |
|---|---|---|
| Mobile | < 48rem | Une colonne |
| Tablette | 48–70rem | Paliers intermédiaires à `60rem` et `70rem` |
| Bureau | > 70rem | Navigation latérale dépliée |

- Navigation latérale repliée sous `760px`.

## 10 Écarts propres à l'application

| Élément | Écart | Motif |
|---|---|---|
| Polices | `caveat-brush/` présente dans les assets | Non utilisée (décision commune) : à retirer |
| Couleurs | `--danger` en `#b42318`, hors charte | Passer au rouge de la charte `#d03f15` |
| Couleurs | 139 valeurs hexadécimales codées en dur dans `app.css` | Remplacement progressif par les variables |
| Focus | Un contour en `--sgdf-mint` (contraste insuffisant), les autres en `--sgdf-blue-light`, sans variable `--focus` | Introduire `--focus` partout |
| Messages | Les flashs s'affichent encore dans la page | Passage aux pop-ups de §07 à faire (`etat.md`) |
