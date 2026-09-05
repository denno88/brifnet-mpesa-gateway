<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\UnixClock;

final class FakeUnixClock implements UnixClock
{
    public function __construct(
        public int $currentTime,
    ) {
    }

    public function now(): int
    {
        return $this->currentTime;
    }
}
