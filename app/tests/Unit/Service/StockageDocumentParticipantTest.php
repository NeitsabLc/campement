<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\StockageDocumentParticipant;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class StockageDocumentParticipantTest extends TestCase
{
    public function testLaReconciliationIgnoreLesFichiersRecentsEtNonGeres(): void
    {
        $repertoire = sys_get_temp_dir().'/campement-documents-'.bin2hex(random_bytes(6));
        self::assertTrue(mkdir($repertoire, 0700));
        $ancien = Uuid::v7()->toRfc4122().'.pdf';
        $recent = Uuid::v7()->toRfc4122().'.jpg';
        file_put_contents($repertoire.'/'.$ancien, 'ancien');
        file_put_contents($repertoire.'/'.$recent, 'recent');
        file_put_contents($repertoire.'/notes.txt', 'hors stockage');
        touch($repertoire.'/'.$ancien, time() - 7200);

        try {
            $stockage = new StockageDocumentParticipant($repertoire);
            self::assertSame([$ancien, $recent], $stockage->listerFichiers());
            self::assertSame([$ancien], $stockage->listerFichiers(new \DateTimeImmutable('-1 hour')));
        } finally {
            foreach (glob($repertoire.'/*') ?: [] as $fichier) {
                unlink($fichier);
            }
            rmdir($repertoire);
        }
    }
}
