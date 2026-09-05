<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\Clock;

final class FakeClock implements Clock
{
    public function __construct(
        private readonly string $currentTimestamp,
    ) {
    }

    public function timestamp(): string
    {
        return $this->currentTimestamp;
    }
}
