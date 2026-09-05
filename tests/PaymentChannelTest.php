<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\PaymentChannel;
use PHPUnit\Framework\TestCase;

final class PaymentChannelTest extends TestCase
{
    public function testPaymentChannelsHaveExpectedValues(): void
    {
        $this->assertSame('STK', PaymentChannel::STK->value);
        $this->assertSame('C2B', PaymentChannel::C2B->value);
    }
}
