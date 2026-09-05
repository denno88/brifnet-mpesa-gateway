<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\PaymentReference;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaymentReferenceTest extends TestCase
{
    public function testValidReferenceIsAccepted(): void
    {
        $reference = new PaymentReference('BRIF-001');

        $this->assertSame('BRIF-001', $reference->value);
    }

    public function testEmptyReferenceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Payment reference cannot be empty.'
        );

        new PaymentReference('');
    }

    public function testWhitespaceOnlyReferenceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Payment reference cannot be empty.'
        );

        new PaymentReference('   ');
    }
}
