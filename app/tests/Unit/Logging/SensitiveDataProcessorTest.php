<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging;

use App\Logging\SensitiveDataProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class SensitiveDataProcessorTest extends TestCase
{
    public function testItRedactsSensitiveRoutesAndQueryParameters(): void
    {
        $distributionToken = '0198a5ae-3ea1-7000-8000-123456789abc';
        $resetToken = str_repeat('a', 64);
        $processor = new SensitiveDataProcessor();

        $record = $processor(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'request',
            level: Level::Error,
            message: sprintf(
                'GET /distribution/%s?email=personne@example.org puis /reinitialiser-mot-de-passe/%s',
                $distributionToken,
                $resetToken,
            ),
            context: ['request_uri' => '/login?token=secret&retour=%2F'],
        ));

        self::assertStringNotContainsString($distributionToken, $record->message);
        self::assertStringNotContainsString($resetToken, $record->message);
        self::assertStringNotContainsString('personne@example.org', $record->message);
        self::assertSame('/login?token=[MASQUE]&retour=[MASQUE]', $record->context['request_uri']);
    }

    public function testItRedactsSensitiveKeysAtEveryDepth(): void
    {
        $processor = new SensitiveDataProcessor();
        $record = $processor(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Warning,
            message: 'Échec contrôlé',
            context: [
                'route_parameters' => ['jeton' => 'valeur-secrete', 'id' => 'identifiant-public'],
                'headers' => ['authorization' => 'Bearer secret', 'accept' => 'text/html'],
                'database' => 'postgresql://campement_app:secret@database/campement',
            ],
            extra: ['client_ip' => '192.0.2.1'],
        ));

        self::assertSame('[MASQUE]', $record->context['route_parameters']['jeton']);
        self::assertSame('identifiant-public', $record->context['route_parameters']['id']);
        self::assertSame('[MASQUE]', $record->context['headers']['authorization']);
        self::assertSame('text/html', $record->context['headers']['accept']);
        self::assertSame('postgresql://campement_app:[MASQUE]@database/campement', $record->context['database']);
        self::assertSame('[MASQUE]', $record->extra['client_ip']);
    }
}
