#!/usr/bin/env python3
"""Vérifier qu'une relance manuelle de la publication déclenche bien la recette.

En `workflow_dispatch`, `build-candidates` est sauté. Sans fonction de statut, ce saut se
propage aux jobs suivants : la promotion et la recette seraient sautées, alors
que le run paraît vert. Chaque job concerné doit donc tester explicitement le
résultat de ses dépendances directes, plus `!cancelled()`. Lecture du texte
seule, sans dépendance externe.
"""
from pathlib import Path
import re
import unittest

RACINE = Path(__file__).resolve().parents[2]
WORKFLOW = RACINE / ".github/workflows/publish-images.yml"
JOBS = {'promote-version': ['validate-release', 'test-candidates'], 'deploy-staging': ['validate-release', 'test-candidates', 'promote-version']}


def bloc_job(texte, job):
    debut = re.search(rf"^  {re.escape(job)}:\n", texte, re.M)
    if debut is None:
        raise AssertionError(f"job introuvable : {job}")
    suite = re.search(r"^  [a-z][a-z0-9-]*:\n", texte[debut.end():], re.M)
    return texte[debut.end(): debut.end() + suite.start() if suite else len(texte)]


def condition(bloc):
    trouve = re.search(r"^    if: >-\n((?: {6}[^\n]*\n)+)", bloc, re.M)
    return " ".join(trouve.group(1).split()) if trouve else ""


class RelancePublicationTest(unittest.TestCase):
    def setUp(self):
        self.texte = WORKFLOW.read_text(encoding="utf-8")

    def test_la_construction_est_sautee_hors_release(self):
        self.assertIn("if: github.event_name == 'release'", bloc_job(self.texte, "build-candidates"))

    def test_les_tests_tournent_en_relance_manuelle(self):
        self.assertIn("github.event_name == 'workflow_dispatch'", bloc_job(self.texte, "test-candidates"))

    def test_promotion_et_recette_testent_leurs_dependances(self):
        for job, dependances in JOBS.items():
            with self.subTest(job=job):
                attendu = " && ".join([f"needs.{d}.result == 'success'" for d in dependances] + ["!cancelled()"])
                self.assertEqual(condition(bloc_job(self.texte, job)), attendu)


if __name__ == "__main__":
    unittest.main()
