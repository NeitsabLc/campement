<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

final class PostgreSqlDateTimeTzImmutableType extends DateTimeTzImmutableType
{
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        foreach (['Y-m-d H:i:s.uP', 'Y-m-d H:i:sP', $platform->getDateTimeTzFormatString()] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, (string) $value);
            if (false !== $date) {
                return $date;
            }
        }

        throw InvalidFormat::new((string) $value, self::class, 'Y-m-d H:i:s[.u]P');
    }
}
