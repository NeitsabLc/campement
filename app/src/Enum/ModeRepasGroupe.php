<?php

declare(strict_types=1);

namespace App\Enum;

enum ModeRepasGroupe: string
{
    case EXPLO = 'EXPLO';
    case PIQUE_NIQUE_1 = 'PIQUE_NIQUE_1';
    case PIQUE_NIQUE_2 = 'PIQUE_NIQUE_2';
    case NON_PRIS = 'NON_PRIS';

    public function libelle(): string
    {
        return match ($this) {
            self::EXPLO => 'Explo',
            self::PIQUE_NIQUE_1 => 'Pique-nique 1',
            self::PIQUE_NIQUE_2 => 'Pique-nique 2',
            self::NON_PRIS => 'Repas non pris',
        };
    }
}
