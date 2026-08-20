<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PresentationMenu;
use PHPUnit\Framework\TestCase;

final class PresentationMenuTest extends TestCase
{
    public function testLaCategorieDesRecettesDependDuTypeDeRepas(): void
    {
        $presentation = new PresentationMenu();

        self::assertSame('PETIT_DEJEUNER', $presentation->categorieRecettesPourRepas('PETIT_DEJEUNER'));
        self::assertSame('GOUTER', $presentation->categorieRecettesPourRepas('GOUTER'));
        self::assertNull($presentation->categorieRecettesPourRepas('DEJEUNER'));
        self::assertNull($presentation->categorieRecettesPourRepas('DINER'));
    }
}
