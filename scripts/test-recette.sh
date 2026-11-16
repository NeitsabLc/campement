#!/bin/sh
# Lance la suite de recette (tests/recette/) sans saisie : adresse de la recette et
# adresse du compte de contrôle lues dans un fichier local ignoré par Git, mot de passe
# lu dans le trousseau macOS au moment du lancement. Les variables déjà définies
# dans l'environnement restent prioritaires. Voir docs/context/infra.md.
set -eu

SERVICE_TROUSSEAU="campement-recette-controle"

racine=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
# RECETTE_FICHIER : autre emplacement du fichier local (sert aux tests du lanceur).
fichier="${RECETTE_FICHIER:-$racine/.local/recette.env}"

echec() {
  printf 'test-recette : %s\n' "$1" >&2
  exit 1
}

# Lit « NOM=valeur » dans le fichier local, sans jamais l'exécuter.
lire() {
  [ -f "$fichier" ] || return 0
  sed -n "s/^$1=//p" "$fichier" | head -n 1
}

[ -z "${CI:-}" ] || echec "refus de démarrer, cette suite ne se lance jamais en CI."

if [ -f "$fichier" ] && grep -Eq '^[[:space:]]*(export[[:space:]]+)?RECETTE_MOT_DE_PASSE=' "$fichier"; then
  echec "le mot de passe ne s'écrit pas dans le fichier local, il reste dans le trousseau."
fi

RECETTE_URL="${RECETTE_URL:-$(lire RECETTE_URL)}"
RECETTE_EMAIL="${RECETTE_EMAIL:-$(lire RECETTE_EMAIL)}"
if [ -z "$RECETTE_URL" ] || [ -z "$RECETTE_EMAIL" ]; then
  echec "RECETTE_URL ou RECETTE_EMAIL absent : les définir, ou les écrire dans .local/recette.env (docs/context/infra.md)."
fi

if [ -z "${RECETTE_MOT_DE_PASSE:-}" ]; then
  command -v security >/dev/null 2>&1 || echec "trousseau macOS indisponible : définir RECETTE_MOT_DE_PASSE."
  # Recherche par service seul : l'entrée est propre à cette suite, quel que soit son attribut de compte.
  RECETTE_MOT_DE_PASSE=$(security find-generic-password -s "$SERVICE_TROUSSEAU" -w 2>/dev/null) \
    || echec "entrée « $SERVICE_TROUSSEAU » absente ou illisible dans le trousseau."
  [ -n "$RECETTE_MOT_DE_PASSE" ] || echec "entrée « $SERVICE_TROUSSEAU » vide dans le trousseau."
fi

export RECETTE_URL RECETTE_EMAIL RECETTE_MOT_DE_PASSE
cd "$racine"
exec npm run test:recette
