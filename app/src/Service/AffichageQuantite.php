<?php

declare(strict_types=1);

namespace App\Service;

final class AffichageQuantite
{
    private const TOLERANCE = 0.0005;
    private const DENOMINATEUR_MAXIMUM = 8;

    public function parPersonne(float|int|string $quantite): string
    {
        $nombre = (float) $quantite;

        if ($nombre > 0.0 && $nombre < 1.0) {
            for ($denominateur = 2; $denominateur <= self::DENOMINATEUR_MAXIMUM; ++$denominateur) {
                $numerateur = (int) round($nombre * $denominateur);
                if ($numerateur > 0
                    && $numerateur < $denominateur
                    && 1 === $this->pgcd($numerateur, $denominateur)
                    && abs($nombre - $numerateur / $denominateur) <= self::TOLERANCE
                ) {
                    return $numerateur.'/'.$denominateur;
                }
            }
        }

        $arrondi = round($nombre, 3);
        if (abs($arrondi - round($arrondi)) < self::TOLERANCE) {
            return (string) (int) round($arrondi);
        }

        return rtrim(rtrim(number_format($arrondi, 3, ',', ''), '0'), ',');
    }

    private function pgcd(int $a, int $b): int
    {
        while (0 !== $b) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
