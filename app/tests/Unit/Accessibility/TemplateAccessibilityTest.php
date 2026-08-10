<?php

declare(strict_types=1);

namespace App\Tests\Unit\Accessibility;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class TemplateAccessibilityTest extends TestCase
{
    public function testChaqueDialoguePossedeUnNomAccessible(): void
    {
        $repertoire = dirname(__DIR__, 3).'/templates';
        $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repertoire));
        $dialoguesTrouves = 0;

        foreach ($fichiers as $fichier) {
            if (!$fichier->isFile() || 'twig' !== $fichier->getExtension()) {
                continue;
            }

            $contenu = file_get_contents($fichier->getPathname());
            self::assertIsString($contenu);
            preg_match_all('/<dialog\b[^>]*>/i', $contenu, $dialogues);

            foreach ($dialogues[0] as $dialogue) {
                ++$dialoguesTrouves;
                self::assertMatchesRegularExpression(
                    '/\saria-(?:label|labelledby)="[^"]+"/i',
                    $dialogue,
                    sprintf('Le dialogue de %s doit posséder un nom accessible.', $fichier->getPathname()),
                );

                if (preg_match('/\saria-labelledby="([^"]+)"/i', $dialogue, $correspondance)) {
                    self::assertStringContainsString(
                        'id="'.$correspondance[1].'"',
                        $contenu,
                        sprintf('Le titre %s doit exister dans %s.', $correspondance[1], $fichier->getPathname()),
                    );
                }
            }
        }

        self::assertGreaterThan(0, $dialoguesTrouves);
    }

    public function testChaquePseudoTableauDeclareSesEntetesLignesEtCellules(): void
    {
        $repertoire = dirname(__DIR__, 3).'/templates';
        $pseudoTableaux = [
            [
                $repertoire.'/components/list_table.html.twig',
                $repertoire.'/fournisseur/index.html.twig',
                $repertoire.'/utilisateur/index.html.twig',
            ],
            [$repertoire.'/recette/index.html.twig'],
            [$repertoire.'/situation_particuliere/liste.html.twig'],
        ];

        foreach ($pseudoTableaux as $fichiers) {
            $contenu = '';
            foreach ($fichiers as $fichier) {
                $modele = file_get_contents($fichier);
                self::assertIsString($modele);
                $contenu .= $modele;
            }

            self::assertStringContainsString('role="table"', $contenu);
            self::assertStringContainsString('role="columnheader"', $contenu);
            self::assertStringContainsString('role="row"', $contenu);
            self::assertStringContainsString('role="cell"', $contenu);
        }
    }
}
