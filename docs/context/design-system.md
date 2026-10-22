# Design system — Campement

> Charte graphique Scouts et Guides de France (SGDF), déjà appliquée dans l'application.
> Source des valeurs : `app/assets/styles/app.css` (`:root`). Toute nouvelle valeur passe d'abord par une variable.

## 01 Logo

| Fichier | Usage |
|---|---|
| `app/assets/images/logo-sgdf-horizontal.png` | En-têtes, page de connexion, écrans de distribution |
| `app/assets/images/logo-sgdf-vertical.png` | Espaces étroits, mise en page verticale |
| `app/assets/images/favicon.svg` | Icône d'onglet |

- Ne pas déformer, recolorer ni recadrer le logo ; garder une zone de respiration autour.
- Toujours avec un texte alternatif (`alt="Scouts et Guides de France"`).

## 02 Typographie

| Rôle | Police | Variable | Repli |
|---|---|---|---|
| Titres, navigation, boutons | Raleway (400, 700) | `--font-title` | "Segoe UI", Arial, sans-serif |
| Texte courant, formulaires | Sarabun (400, 700, italique) | `--font-body` | "Segoe UI", Arial, sans-serif |

- Polices auto-hébergées dans `app/assets/fonts/` (aucun appel à un service externe).
- `caveat-brush/` est présente mais inutilisée : ne pas l'employer sans décision.
- Tailles en `rem` ; texte courant ≥ 1rem, mentions et métadonnées ≥ 0.75rem.

## 03 Couleurs

| Variable | Valeur | Usage |
|---|---|---|
| `--sgdf-blue` | `#003a5d` | Couleur principale : titres, navigation, boutons primaires |
| `--sgdf-blue-light` | `#0077b3` | Liens, éléments actifs |
| `--sgdf-mint` | `#65bc99` | Accent, focus clavier, succès |
| `--surface` | `#ffffff` | Fond des cartes et formulaires |
| `--surface-muted` | `#f4f7f9` | Fond de page, survol des lignes |
| `--border` | `#d9e2e7` | Bordures, séparateurs |
| `--text-muted` | `#526875` | Texte secondaire |
| `--danger` | `#b42318` | Erreurs, actions destructives |
| `--danger-surface` | `#fef3f2` | Fond des messages d'erreur |

- Contraste minimal 4.5:1 pour le texte (3:1 pour les grands titres et les icônes).
- Ne jamais transmettre une information par la couleur seule (ajouter texte ou icône).
- Des couleurs codées en dur existent encore dans `app.css` : les remplacer par une variable quand on touche le bloc.

## 04 Icônes

- SVG en ligne dans les gabarits, `fill`/`stroke` à `currentColor`, taille alignée sur le texte (1em à 1.25rem).
- Icône décorative : `aria-hidden="true"` ; bouton composé d'une seule icône : `aria-label` obligatoire.
- Réutiliser une icône déjà présente avant d'en dessiner une nouvelle ; style linéaire, trait régulier.

## 05 Composants et mise en page

| Élément | Règle |
|---|---|
| Boutons | `.button`, `.button--primary` (bleu SGDF), `.button--danger-outline` ; un seul bouton primaire par zone |
| Rayons | `0.3rem` (champs, boutons), `0.45rem` (cartes), `50%` (pastilles) |
| Ombre | `--shadow` réservée aux éléments flottants (dialogues, menus) |
| Focus | `outline: 3px solid var(--sgdf-mint)` + `outline-offset: 2px`, jamais supprimé |
| Points de rupture | `48rem` (mobile), `60rem` / `70rem` (tablette) ; navigation latérale repliée sous `760px` |
| Tableaux | `components/list_table.html.twig` et `sort_indicator.html.twig` pour les listes triables |

## 06 Messages et retours utilisateur

| Type | Affichage | Durée |
|---|---|---|
| Succès, information | Pop-up en haut à droite (`role="status"`) | Disparaît après **3 s** |
| Erreur générale | Pop-up en haut à droite (`role="alert"`) | Disparaît après **3 s** |
| Erreur de champ | Sous le champ concerné, liée par `aria-describedby`, champ en `aria-invalid="true"` | Reste jusqu'à correction |

- Pop-ups : bouton de fermeture, pause du minuteur au survol et au focus, empilement de haut en bas.
- Messages courts, en français, orientés action (« Saisissez une date de fin postérieure au début »).
- **État actuel** : les messages flash s'affichent encore dans la page ; la mise en place des pop-ups est à faire (voir `etat.md`).

## 07 Accessibilité

- [ ] Cible WCAG 2.2 AA / RGAA ; contrôlée par `make test-accessibility` (Axe).
- [ ] Chaque champ a un `<label>` visible ; les erreurs sont annoncées.
- [ ] Navigation complète au clavier, ordre logique, focus visible.
- [ ] Zones cliquables d'au moins 24 × 24 px (44 px recommandé sur mobile).
