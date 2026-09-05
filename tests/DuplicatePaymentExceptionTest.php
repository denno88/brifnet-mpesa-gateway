<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\DuplicatePaymentException;
use PHPUnit\Framework\TestCase;

final class DuplicatePaymentExceptionTest extends TestCase
{
    public function testItIsARuntimeException(): void
    {
        $exception = new DuplicatePaymentException(
            'Payment already exists.'
        );

        $this->assertInstanceOf(
            \RuntimeException::class,
            $exception
        );

        $this->assertSame(
            'Payment already exists.',
            $exception->getMessage()
        );
    }
}