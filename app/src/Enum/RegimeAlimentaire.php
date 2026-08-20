<?php

declare(strict_types=1);

namespace App\Enum;

enum RegimeAlimentaire: string
{
    case VEGETARIEN = 'VEGETARIEN';
    case SANS_LACTOSE = 'SANS_LACTOSE';
    case SANS_GLUTEN = 'SANS_GLUTEN';

    public function libelle(): string
    {
        return match ($this) {
            self::VEGETARIEN => 'Végétarien',
            self::SANS_LACTOSE => 'Sans lactose',
            self::SANS_GLUTEN => 'Sans gluten',
        };
    }

    /** @return array<string, string> */
    public static function choix(): array
    {
        $choix = [];
        foreach (self::cases() as $regime) {
            $choix[$regime->value] = $regime->libelle();
        }

        return $choix;
    }
}
