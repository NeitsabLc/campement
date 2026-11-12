#!/usr/bin/env python3
"""Vérifier que les référentiels dont dépend le code viennent des changesets versionnés.

La base de tests applique aussi le contexte « dev » : elle masquerait un référentiel
qui n'existerait que dans les données de développement. Ce test lit donc uniquement
les changesets versionnés, sans Docker ni base de données.
"""
from pathlib import Path
import re
import unittest

RACINE = Path(__file__).resolve().parents[2]
SERVICE = RACINE / "app/src/Service/EnregistrementMouvementStockMultiple.php"
VERSIONNES = RACINE / "database/changelog/versioned"


def origines_utilisees():
    source = SERVICE.read_text(encoding="utf-8")
    bloc = re.search(r"ORIGINES_PAR_TYPE\s*=\s*\[(.*?)\n    \];", source, re.S)
    if bloc is None:
        raise AssertionError("ORIGINES_PAR_TYPE introuvable")
    return set(re.findall(r"'([A-Z][A-Z_]+)'", re.sub(r"'(ENTREE|SORTIE)'\s*=>", "", bloc.group(1))))


def origines_versionnees():
    codes = set()
    for fichier in sorted(VERSIONNES.glob("*.sql")):
        sql = fichier.read_text(encoding="utf-8")
        for insertion in re.findall(r"INSERT\s+INTO\s+campement\.origine_mouvement\b.*?;", sql, re.S | re.I):
            codes.update(re.findall(r"'([A-Z][A-Z_]+)'", insertion))
    return codes


class ReferentielOriginesMouvementTest(unittest.TestCase):
    def test_le_code_utilise_des_origines(self):
        self.assertGreaterEqual(len(origines_utilisees()), 6)

    def test_toutes_les_origines_utilisees_sont_dans_les_changesets_versionnes(self):
        manquantes = origines_utilisees() - origines_versionnees()
        self.assertEqual(set(), manquantes, "Origines absentes des changesets versionnés")


if __name__ == "__main__":
    unittest.main()
