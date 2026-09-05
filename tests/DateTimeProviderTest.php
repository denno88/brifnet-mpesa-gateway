<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\DateTimeProvider;
use PHPUnit\Framework\TestCase;

final class DateTimeProviderTest extends TestCase
{
    public function testDateTimeProviderReturnsDateTimeImmutable(): void
    {
        $provider = new DateTimeProviderFake();

        $result = $provider->now();

        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $result
        );
    }
}

final class DateTimeProviderFake implements DateTimeProvider
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            '2026-09-04 15:00:00',
            new \DateTimeZone('Africa/Nairobi')
        );
    }
}