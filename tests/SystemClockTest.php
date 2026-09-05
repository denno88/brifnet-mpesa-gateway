<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\Clock;
use BrifnetMpesa\Api\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testSystemClockReturnsDarajaTimestampFormat(): void
    {
        $clock = new SystemClock();

        $this->assertInstanceOf(
            Clock::class,
            $clock
        );

        $timestamp = $clock->timestamp();

        $this->assertMatchesRegularExpression(
            '/^\d{14}$/',
            $timestamp
        );
    }
}
