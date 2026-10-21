#!/usr/bin/env python3
"""Tester le refus des anciens runtimes sans lancer Docker ou PHP applicatif."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

GUARD = Path(__file__).resolve().parents[2] / "docker/php/verify-php-security.sh"


class PhpSecurityTest(unittest.TestCase):
    def check_version(self, version, php_exit="0"):
        with tempfile.TemporaryDirectory() as directory:
            fake = Path(directory) / "php"
            fake.write_text('#!/bin/sh\nprintf "%s" "$TEST_PHP_VERSION_ID"\nexit "$TEST_PHP_EXIT"\n')
            fake.chmod(0o755)
            env = dict(os.environ, PATH=directory + os.pathsep + os.environ["PATH"],
                       TEST_PHP_VERSION_ID=version, TEST_PHP_EXIT=php_exit)
            return subprocess.run(["sh", str(GUARD)], env=env, capture_output=True, text=True)

    def test_corrected_84_versions(self):
        for version in ("80427", "80428"):
            with self.subTest(version=version):
                self.assertEqual(self.check_version(version).returncode, 0)

    def test_vulnerable_other_branch_and_invalid_versions(self):
        for version in ("80424", "80426", "80336", "80511", "80512", "", "unknown", "99999999999999999999"):
            with self.subTest(version=version):
                self.assertNotEqual(self.check_version(version).returncode, 0)

    def test_failed_interpreter_is_rejected(self):
        self.assertNotEqual(self.check_version("80427", "1").returncode, 0)


if __name__ == "__main__":
    unittest.main()

