<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkPasswordGenerator;
use PHPUnit\Framework\TestCase;

final class StkPasswordGeneratorTest extends TestCase
{
    public function testPasswordIsGeneratedFromDarajaCredentials(): void
    {
        $generator = new StkPasswordGenerator();

        $password = $generator->generate(
            businessShortCode: '174379',
            passkey: 'test-passkey',
            timestamp: '20260902130000',
        );

        $this->assertSame(
            base64_encode(
                '174379test-passkey20260902130000'
            ),
            $password
        );
    }
}
