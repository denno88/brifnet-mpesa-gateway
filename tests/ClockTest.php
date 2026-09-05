<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\Clock;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function testFakeClockReturnsConfiguredTimestamp(): void
    {
        $clock = new FakeClock('20260902130000');

        $this->assertInstanceOf(
            Clock::class,
            $clock
        );

        $this->assertSame(
            '20260902130000',
            $clock->timestamp()
        );
    }
}
