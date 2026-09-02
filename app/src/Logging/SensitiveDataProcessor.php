<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

final class SensitiveDataProcessor
{
    private const MASK = '[MASQUE]';

    private const SENSITIVE_KEY_PATTERN = '/(?:^|_)(?:authorization|cookie|credential|csrf|dsn|email|ip|jeton|mot_de_passe|password|passphrase|remote_addr|secret|session|token|user_identifier|username)(?:$|_)/i';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->sanitizeString($record->message),
            context: $this->sanitizeArray($record->context),
            extra: $this->sanitizeArray($record->extra),
        );
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private function sanitizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key)) {
                $values[$key] = self::MASK;
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $values[$key] = $this->sanitizeString($value);
            }
        }

        return $values;
    }

    private function sanitizeString(string $value): string
    {
        return preg_replace(
            [
                '#(/distribution/)[0-9a-fA-F-]{36}(?=[/\\s?&\\#"\']|$)#',
                '#(/reinitialiser-mot-de-passe/)[0-9a-fA-F]{64}(?=[/\\s?&\\#"\']|$)#',
                '#([?&][A-Za-z0-9_.%~-]+)=([^&\\s"\'<>]+)#',
                '#\\b(?:Bearer|Basic)\\s+[A-Za-z0-9._~+/=-]+#i',
                '#((?:postgres(?:ql)?|mysql|smtp|smtps)://[^:/\\s]+:)[^@\\s]+@#i',
                '#\\b(PHPSESSID=)[^;\\s]+#i',
                '#[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}#i',
            ],
            [
                '$1'.self::MASK,
                '$1'.self::MASK,
                '$1='.self::MASK,
                self::MASK,
                '$1'.self::MASK.'@',
                '$1'.self::MASK,
                self::MASK,
            ],
            $value,
        ) ?? self::MASK;
    }
}
