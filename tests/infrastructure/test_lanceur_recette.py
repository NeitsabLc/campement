#!/usr/bin/env python3
"""Tester le lanceur de la suite de recette sans trousseau, sans réseau et sans navigateur."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

LANCEUR = Path(__file__).resolve().parents[2] / "scripts/test-recette.sh"
SECRET = "mot-de-passe-factice"


class LanceurRecetteTest(unittest.TestCase):
    def lancer(self, fichier=None, trousseau=True, **variables):
        with tempfile.TemporaryDirectory() as dossier:
            dossier = Path(dossier)
            # Faux npm : écrit ce qu'il reçoit, sans rien lancer.
            (dossier / "npm").write_text(
                '#!/bin/sh\nprintf "%s|%s|%s|%s\\n" "$*" "$RECETTE_URL" "$RECETTE_EMAIL" "$RECETTE_MOT_DE_PASSE"\n'
            )
            # Faux trousseau : répond seulement à la lecture attendue.
            (dossier / "security").write_text(
                '#!/bin/sh\n[ "$TEST_TROUSSEAU" = "1" ] || exit 44\n'
                '[ "$1 $2 $4" = "find-generic-password -s -w" ] || exit 2\n'
                'printf "%s:%s\\n" "$3" "$TEST_SECRET"\n'
            )
            for nom in ("npm", "security"):
                (dossier / nom).chmod(0o755)
            local = dossier / "recette.env"
            if fichier is not None:
                local.write_text(fichier)
            env = {
                "PATH": str(dossier) + os.pathsep + "/usr/bin:/bin",
                "RECETTE_FICHIER": str(local),
                "TEST_TROUSSEAU": "1" if trousseau else "0",
                "TEST_SECRET": SECRET,
                **variables,
            }
            return subprocess.run(["sh", str(LANCEUR)], env=env, capture_output=True, text=True)

    def test_fichier_local_et_trousseau(self):
        resultat = self.lancer("# commentaire\nRECETTE_URL=https://recette.exemple.test\nRECETTE_EMAIL=controle@exemple.test\n")
        self.assertEqual(resultat.returncode, 0, resultat.stderr)
        commande, url, email, mot_de_passe = resultat.stdout.strip().split("|")
        self.assertEqual(commande, "run test:recette")
        self.assertEqual(url, "https://recette.exemple.test")
        self.assertEqual(email, "controle@exemple.test")
        self.assertRegex(mot_de_passe, r"^[a-z-]+-recette-controle:" + SECRET + "$")

    def test_environnement_prioritaire(self):
        resultat = self.lancer(
            "RECETTE_URL=https://recette.exemple.test\nRECETTE_EMAIL=controle@exemple.test\n",
            trousseau=False,
            RECETTE_URL="https://autre-recette.exemple.test",
            RECETTE_EMAIL="autre@exemple.test",
            RECETTE_MOT_DE_PASSE="fourni",
        )
        self.assertEqual(resultat.returncode, 0, resultat.stderr)
        self.assertEqual(
            resultat.stdout.strip(),
            "run test:recette|https://autre-recette.exemple.test|autre@exemple.test|fourni",
        )

    def test_refus_sans_adresse(self):
        for fichier in (None, "RECETTE_URL=https://recette.exemple.test\n", "RECETTE_EMAIL=controle@exemple.test\n"):
            with self.subTest(fichier=fichier):
                resultat = self.lancer(fichier)
                self.assertNotEqual(resultat.returncode, 0)
                self.assertEqual(resultat.stdout, "")
                self.assertIn(".local/recette.env", resultat.stderr)

    def test_refus_trousseau_illisible(self):
        resultat = self.lancer("RECETTE_URL=https://recette.exemple.test\nRECETTE_EMAIL=controle@exemple.test\n", trousseau=False)
        self.assertNotEqual(resultat.returncode, 0)
        self.assertEqual(resultat.stdout, "")
        self.assertIn("trousseau", resultat.stderr)

    def test_refus_mot_de_passe_dans_le_fichier(self):
        for prefixe in ("", "export ", "  "):
            with self.subTest(prefixe=prefixe):
                resultat = self.lancer(
                    "RECETTE_URL=https://recette.exemple.test\nRECETTE_EMAIL=controle@exemple.test\n"
                    + prefixe + "RECETTE_MOT_DE_PASSE=" + SECRET + "\n"
                )
                self.assertNotEqual(resultat.returncode, 0)
                self.assertEqual(resultat.stdout, "")
                self.assertNotIn(SECRET, resultat.stderr)

    def test_refus_en_ci(self):
        resultat = self.lancer("RECETTE_URL=https://recette.exemple.test\nRECETTE_EMAIL=controle@exemple.test\n", CI="true")
        self.assertNotEqual(resultat.returncode, 0)
        self.assertEqual(resultat.stdout, "")


if __name__ == "__main__":
    unittest.main()
