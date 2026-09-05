<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    public function testPaymentStatusesHaveExpectedValues(): void
    {
        $this->assertSame('PENDING', PaymentStatus::PENDING->value);
        $this->assertSame('COMPLETED', PaymentStatus::COMPLETED->value);
        $this->assertSame('FAILED', PaymentStatus::FAILED->value);
    }
}